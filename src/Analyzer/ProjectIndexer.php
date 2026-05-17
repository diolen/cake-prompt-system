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
        // Создаем парсер, совместимый с PHP 5.6 и выше
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->extractor = $extractor;
        
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->extractor);
    }

/**
     * Сканирует директорию проекта и строит граф входящих связей
     */
    public function indexProject(string $projectPath): void
    {
        if (!is_dir($projectPath)) {
            return;
        }

        $directory = new \RecursiveDirectoryIterator($projectPath);
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $fileInfo) {
            // 1. Проверяем, что это файл, а не ссылка/директория, и у него расширение .php
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $filePath = $fileInfo->getRealPath();

            // 2. Игнорируем папку vendor и скрытые директории вроде .git
            if (str_contains($filePath, '/vendor/') || str_contains($filePath, '/.')) {
                continue;
            }

            try {
                $code = file_get_contents($filePath);
                $ast = $this->parser->parse($code);
                if (!$ast) {
                    continue;
                }

                // Очищаем экстрактор перед обходом нового файла
                $this->extractor->clear();
                $this->traverser->traverse($ast);

                // Получаем все зависимости текущего файла
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
                // Игнорируем битые файлы, чтобы не прерывать общий индекс
                continue;
            }
        }
    }

    /**
     * Возвращает Influence Score для конкретного класса (модели/компонента)
     */
    public function getInfluenceScore(string $className): int
    {
        $cleanName = $this->sanitizeClassName($className);
        return isset($this->inverseGraph[$cleanName]) ? count($this->inverseGraph[$cleanName]) : 0;
    }

    /**
     * Возвращает список компонентов, которые зависят от данного класса
     */
    public function getDependentComponents(string $className): array
    {
        $cleanName = $this->sanitizeClassName($className);
        return $this->inverseGraph[$cleanName] ?? [];
    }

    /**
     * Вытаскивает имя класса из пути (в CakePHP v2 имя файла == имя класса)
     */
    private function deriveClassName(string $filePath): ?string
    {
        return basename($filePath, '.php');
    }

    /**
     * Хелпер для очистки суффиксов, если мы запрашиваем модель по имени файла контроллера
     * (например, для UsersController нам нужно искать связи сущности 'Users' или 'User')
     */
    private function sanitizeClassName(string $className): string
    {
        return str_replace('Controller', '', $className);
    }
}