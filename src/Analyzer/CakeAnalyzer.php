<?php

namespace App\Analyzer;

use Exception;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

class CakeAnalyzer
{
    private string $filePath;
    private string $code;
    private ProjectIndexer $indexer;

    // ProjectIndexer is now required in constructor
    public function __construct(string $filePath, ProjectIndexer $indexer)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found for analysis: {$filePath}");
        }

        $this->filePath = $filePath;
        $this->code = file_get_contents($filePath);
        $this->indexer = $indexer;
    }

    public function analyze(): array
    {
        $layer = $this->detectLayer();
        $version = $this->detectCakeVersion($layer);
        $className = basename($this->filePath, '.php');

        // By default, relationships are empty
        $models = [];
        $components = [];
        $associations = [];

        // Run AST parser for CakePHP v2
        if ($version === 'v2' && ($layer === 'Controller' || $layer === 'Model')) {
            // Use factory to create parser for current php-parser version
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            
            try {
                $ast = $parser->parse($this->code);
                $traverser = new NodeTraverser();

                if ($layer === 'Controller') {
                    // Extract controller properties ($uses, $components, loadModel)
                    $extractor = new CakeV2PropertyExtractor();
                    $traverser->addVisitor($extractor);
                    $traverser->traverse($ast);

                    $models = $extractor->getModels();
                    $components = $extractor->getComponents();

                } elseif ($layer === 'Model') {
                    // DEEP MODEL ANALYSIS: Extract DB relationships ($belongsTo, $hasMany, etc.)
                    $modelExtractor = new CakeV2ModelExtractor();
                    $traverser->addVisitor($modelExtractor);
                    $traverser->traverse($ast);

                    $associations = $modelExtractor->getAssociations();
                }
                
            } catch (\Throwable $e) {
                // Ignore syntax errors in legacy files, returning empty structures
            }
        }

        // Calculate Connectivity metric (Connectivity / Outgoing relationships)
        $connectivity = ($layer === 'Controller') 
            ? count($models) + count($components) 
            : $this->countAssociations($associations);

        // Get influence metrics from global indexer (Incoming relationships)
        $influenceScore = $this->indexer->getInfluenceScore($className);
        $impactedBy = $this->indexer->getDependentComponents($className);

        return [
            'system_meta' => [
                'framework' => 'CakePHP',
                'framework_version' => $version,
                'php_version' => '5.6'
            ],
            'task_context' => [
                'target_layer' => $layer,
                'file_path' => $this->filePath,
                'class_name' => $className
            ],
            'metrics' => [
                'connectivity' => $connectivity,
                'influence_score' => $influenceScore // Now calculated dynamically!
            ],
            'relations' => [
                'models' => $models,
                'components' => $components,
                'associations' => $associations
            ],
            'impacted_by' => $impactedBy // Add list of files that depend on current one
        ];
    }

    /**
     * Determines architectural layer (Controller or Model)
     */
    private function detectLayer(): string
    {
        $fileName = basename($this->filePath);

        if (str_contains($fileName, 'Controller')) {
            return 'Controller';
        }

        if (
            str_contains($this->filePath, '/Model/') || 
            str_contains($fileName, 'Table') || 
            str_contains($fileName, 'Entity')
        ) {
            return 'Model';
        }

        // FALLBACK OPTION: Analyze file contents
        if (preg_match('/class\s+\w+\s+extends\s+(AppModel|Model)/i', $this->code)) {
            return 'Model';
        }

        return 'Unknown';
    }

    /**
     * Determines CakePHP version (v2, v3, v4)
     */
    private function detectCakeVersion(string $layer): string
    {
        if (str_contains($this->code, 'namespace App\\')) {
            return str_contains($this->code, 'declare(strict_types=1);') ? 'v4' : 'v3';
        }

        return 'v2';
    }

    /**
     * Helper for counting total model associations
     */
    private function countAssociations(array $associations): int
    {
        $count = 0;
        foreach ($associations as $type => $relations) {
            if (is_array($relations)) {
                $count += count($relations);
            }
        }
        return $count;
    }
}