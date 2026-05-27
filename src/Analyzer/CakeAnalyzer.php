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
        $validate = [];  // Will be populated for Models only
        $helpers = [];
        $elements = [];
        $variables = [];
        $controllerName = '';
        $elementContext = [];  // Will be converted to object for non-View layers

        // Run AST parser for CakePHP v2
        if ($version === 'v2' && ($layer === 'Controller' || $layer === 'Model' || $layer === 'View')) {
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
                    $validate = $modelExtractor->getValidate();

                } elseif ($layer === 'View') {
                    // VIEW ANALYSIS: Extract helpers, elements, variables
                    $projectRoot = $this->findProjectRoot();
                    $viewExtractor = new CakeV2ViewExtractor($this->filePath, $projectRoot);
                    $traverser->addVisitor($viewExtractor);
                    $traverser->traverse($ast);

                    $helpers = $viewExtractor->getHelpers();
                    $elements = $viewExtractor->getElements();
                    $variables = $viewExtractor->getVariables();
                    $controllerName = $viewExtractor->getControllerName();
                    $elementContext = $viewExtractor->getElementContext();

                    // Try to extract controller context for richer View information
                    if (!empty($controllerName)) {
                        $controllerPath = $this->findControllerPath($controllerName);
                        if ($controllerPath && file_exists($controllerPath)) {
                            $controllerCode = file_get_contents($controllerPath);
                            try {
                                $controllerAst = $parser->parse($controllerCode);
                                $controllerTraverser = new NodeTraverser();
                                $controllerExtractor = new CakeV2PropertyExtractor();
                                $controllerTraverser->addVisitor($controllerExtractor);
                                $controllerTraverser->traverse($controllerAst);

                                $models = $controllerExtractor->getModels();
                                $components = $controllerExtractor->getComponents();
                            } catch (\Throwable $e) {
                                // Ignore controller parsing errors
                            }
                        }
                    }
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
                'associations' => $associations,
                'validate' => $validate,
                'helpers' => $helpers,
                'elements' => $elements,
                'element_context' => $layer === 'View' ? $elementContext : (object)[],
                'variables' => $variables,
                'controller_name' => $controllerName
            ],
            'impacted_by' => $impactedBy // Add list of files that depend on current one
        ];
    }

    /**
     * Finds controller file path based on controller name
     */
    private function findControllerPath(string $controllerName): ?string
    {
        // Extract project root from file path
        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            return null;
        }

        // Try common controller paths
        $possiblePaths = [
            $projectRoot . '/Controller/' . $controllerName . 'Controller.php',
            $projectRoot . '/app/Controller/' . $controllerName . 'Controller.php',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Finds project root from file path
     */
    private function findProjectRoot(): ?string
    {
        $filePath = $this->filePath;

        // Look for common CakePHP structure indicators
        if (str_contains($filePath, '/app/')) {
            return substr($filePath, 0, strpos($filePath, '/app/'));
        }

        if (str_contains($filePath, '/Controller/') || str_contains($filePath, '/Model/') || str_contains($filePath, '/View/')) {
            // Find the directory before Controller/Model/View
            $parts = explode('/', $filePath);
            for ($i = 0; $i < count($parts); $i++) {
                if (in_array($parts[$i], ['Controller', 'Model', 'View'])) {
                    return implode('/', array_slice($parts, 0, $i));
                }
            }
        }

        return null;
    }

    /**
     * Determines architectural layer (Controller, Model, or View)
     */
    private function detectLayer(): string
    {
        $fileName = basename($this->filePath);

        // Check for View files (.ctp extension)
        if (str_ends_with($fileName, '.ctp') || str_contains($this->filePath, '/View/')) {
            return 'View';
        }

        // Check for Controller
        if (str_contains($fileName, 'Controller')) {
            return 'Controller';
        }

        // Check for Model
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