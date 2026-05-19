<?php

namespace App\Analyzer;

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

class ProjectIndexer
{
    private $parser;
    private CakeV2PropertyExtractor $extractor;
    private NodeTraverser $traverser;
    private array $inverseGraph = [];

    public function __construct(CakeV2PropertyExtractor $extractor)
    {
        // Create parser compatible with PHP 5.6 and above
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->extractor = $extractor;
        
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->extractor);
    }

/**
     * Scans project directory and builds incoming relationship graph
     */
    public function indexProject(string $projectPath): void
    {
        if (!is_dir($projectPath)) {
            return;
        }

        $directory = new \RecursiveDirectoryIterator($projectPath);
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $fileInfo) {
            // 1. Check that it's a file, not symlink/directory, and has .php extension
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $filePath = $fileInfo->getRealPath();

            // 2. Ignore vendor folder and hidden directories like .git
            if (str_contains($filePath, '/vendor/') || str_contains($filePath, '/.')) {
                continue;
            }

            try {
                $code = file_get_contents($filePath);
                $ast = $this->parser->parse($code);
                if (!$ast) {
                    continue;
                }

                // Clear extractor before traversing new file
                $this->extractor->clear();
                $this->traverser->traverse($ast);

                // Get all dependencies of current file
                $dependencies = $this->extractor->extractDependencies();
                $className = $this->deriveClassName($filePath);

                if (!$className) {
                    continue;
                }

                foreach ($dependencies as $dep) {
                    if (!isset($this->inverseGraph[$dep])) {
                        $this->inverseGraph[$dep] = [];
                    }
                    if (!in_array($className, $this->inverseGraph[$dep])) {
                        $this->inverseGraph[$dep][] = $className;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore broken files to not interrupt overall index
                continue;
            }
        }
    }

    /**
     * Returns Influence Score for specific class (model/component)
     */
    public function getInfluenceScore(string $className): int
    {
        $cleanName = $this->sanitizeClassName($className);
        return isset($this->inverseGraph[$cleanName]) ? count($this->inverseGraph[$cleanName]) : 0;
    }

    /**
     * Returns list of components that depend on this class
     */
    public function getDependentComponents(string $className): array
    {
        $cleanName = $this->sanitizeClassName($className);
        return $this->inverseGraph[$cleanName] ?? [];
    }

    /**
     * Extracts class name from path (in CakePHP v2 file name == class name)
     */
    private function deriveClassName(string $filePath): ?string
    {
        return basename($filePath, '.php');
    }

    /**
     * Helper for cleaning suffixes if we request model by controller file name
     * (e.g., for UsersController we need to search for entity 'Users' or 'User' relationships)
     */
    private function sanitizeClassName(string $className): string
    {
        return str_replace('Controller', '', $className);
    }
}