<?php

namespace App\Analyzer;

use Exception;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

class CakeAnalyzer
{
    private string $filePath;
    private string $code;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Ошибка: Файл не найден по пути: {$filePath}");
        }
        
        $this->filePath = $filePath;
        $this->code = file_get_contents($filePath);
    }

    public function analyze(): array
    {
        $layer = $this->detectLayer();
        $version = $this->detectCakeVersion($layer);

        // По умолчанию связи пустые
        $models = [];
        $components = [];
        $associations = [];

        // Запускаем AST-парсер для CakePHP v2
        if ($version === 'v2' && ($layer === 'Controller' || $layer === 'Model')) {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            
            try {
                $ast = $parser->parse($this->code);
                
                $traverser = new NodeTraverser();
                $extractor = new CakeV2PropertyExtractor();
                $traverser->addVisitor($extractor);
                
                $traverser->traverse($ast);

                if ($layer === 'Controller') {
                    $models = $extractor->getModels();
                    $components = $extractor->getComponents();
                } elseif ($layer === 'Model') {
                    $associations = $extractor->getAssociations();
                }
                
            } catch (\Throwable $e) {
                // Игнорируем синтаксические ошибки легаси-файлов
            }
        }

        // Расчет метрики Connectivity (Связность)
        $connectivity = ($layer === 'Controller') 
            ? count($models) + count($components) 
            : count($associations);

        return [
            'system_meta' => [
                'framework' => 'CakePHP',
                'framework_version' => $version,
                'php_version' => '5.6'
            ],
            'task_context' => [
                'target_layer' => $layer,
                'file_path' => $this->filePath,
                'class_name' => basename($this->filePath, '.php')
            ],
            'metrics' => [
                'connectivity' => $connectivity,
                'influence_score' => 'UNKNOWN'
            ],
            'relations' => [
                'models' => $models,
                'components' => $components,
                'associations' => $associations // Добавляем новый блок для моделей
            ]
        ];
    }

    /**
     * Определяет архитектурный слой (Controller или Model)
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

        // РЕЗЕРВНЫЙ ВАРИАНТ: Анализ содержимого файла
        // Если класс расширяет AppModel или Model — это 100% модель CakePHP
        if (preg_match('/class\s+\w+\s+extends\s+(AppModel|Model)/i', $this->code)) {
            return 'Model';
        }

        return 'Unknown';
    }

    /**
     * Определяет версию CakePHP (v2, v3, v4)
     */
    private function detectCakeVersion(string $layer): string
    {
        if (str_contains($this->code, 'namespace App\\')) {
            return str_contains($this->code, 'declare(strict_types=1);') ? 'v4' : 'v3';
        }

        return 'v2';
    }
}