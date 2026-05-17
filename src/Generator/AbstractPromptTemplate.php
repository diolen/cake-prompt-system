<?php

namespace App\Generator;

abstract class AbstractPromptTemplate
{
    protected array $analysisResult;

    public function __construct(array $analysisResult)
    {
        $this->analysisResult = $analysisResult;
    }

    /**
     * Каждая задача должна реализовать свою специфичную инструкцию
     */
    abstract public function getTaskInstruction(): string;

    /**
     * Генерирует финальный системный промпт
     */
    public function compile(): string
    {
        // Подгружаем внешний текстовый макет
        $templatePath = __DIR__ . '/Templates/base_layout.txt';
        
        if (!file_exists($templatePath)) {
            // Резервный путь, если файл положили в корень Generator
            $templatePath = __DIR__ . '/base_layout.txt';
        }

        if (!file_exists($templatePath)) {
            return "// [Ошибка системы] Не найден файл шаблона: " . $templatePath;
        }

        $template = file_get_contents($templatePath);

        // Извлекаем исходный код целевого файла
        $sourceCode = $this->extractSourceCode();

        // Формируем карту замен. Передавая массивы в str_replace, 
        // мы гарантируем, что PHP выполнит подстановку за один проход.
        // Это предотвращает рекурсивные и бесконечные замены, если маркеры встретятся внутри кода.
        $replacements = array(
            '[[CONTEXT_JSON]]'     => $this->renderContextJSON(),
            '[[TASK_INSTRUCTION]]' => $this->getTaskInstruction(),
            '[[SOURCE_CODE]]'      => trim($sourceCode) . "\n"
        );

        return str_replace(
            array_keys($replacements), 
            array_values($replacements), 
            $template
        );
    }

    /**
     * Автоматически считывает исходный код анализируемого файла
     */
    private function extractSourceCode(): string
    {
        $filePath = $this->analysisResult['task_context']['file_path'] ?? null;

        if (!$filePath) {
            return '// [Ошибка системы] Путь к файлу не найден в метаконтексте анализа.';
        }

        if (!file_exists($filePath)) {
            return "// [Ошибка системы] Файл не найден по пути: {$filePath}";
        }

        if (!is_readable($filePath)) {
            return "// [Ошибка системы] Файл найден, но недоступен для чтения: {$filePath}";
        }

        return file_get_contents($filePath);
    }

    /**
     * Красиво форматирует JSON для вставки в промпт
     */
    private function renderContextJSON(): string
    {
        return json_encode($this->analysisResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}