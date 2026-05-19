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
     * Each task must implement its specific instruction
     */
    abstract public function getTaskInstruction(): string;

    /**
     * Generates final system prompt considering custom requirements
     */
    public function compile(string $customInstruction = ''): string
    {
        // Load external text template
        $templatePath = __DIR__ . '/Templates/base_layout.txt';
        
        if (!file_exists($templatePath)) {
            // Fallback path if file was placed in Generator root
            $templatePath = __DIR__ . '/base_layout.txt';
        }

        if (!file_exists($templatePath)) {
            return "// [System Error] Template file not found: " . $templatePath;
        }

        $template = file_get_contents($templatePath);

        // Extract source code of target file
        $sourceCode = $this->extractSourceCode();

        // Form base instruction from task subtype strategy
        $taskInstruction = $this->getTaskInstruction();

        // If custom requirements passed from console — inject them into instruction block
        if (!empty($customInstruction)) {
            $taskInstruction .= "\n\n### ⚡ CRITICAL ADDITIONAL USER REQUIREMENTS:\n";
            $taskInstruction .= "When generating code you must unconditionally follow these specific instructions:\n";
            $taskInstruction .= "> " . trim($customInstruction) . "\n";
        }

        // Form replacement map. By passing arrays to str_replace,
        // we guarantee PHP performs substitution in one pass.
        // This prevents recursive and infinite replacements if markers appear inside code.
        $replacements = array(
            '[[CONTEXT_JSON]]'      => $this->renderContextJSON(),
            '[[TASK_INSTRUCTION]]' => $taskInstruction,
            '[[SOURCE_CODE]]'       => trim($sourceCode) . "\n"
        );

        return str_replace(
            array_keys($replacements), 
            array_values($replacements), 
            $template
        );
    }

    /**
     * Automatically reads source code of analyzed file
     */
    private function extractSourceCode(): string
    {
        $filePath = $this->analysisResult['task_context']['file_path'] ?? null;

        if (!$filePath) {
            return '// [System Error] File path not found in analysis metacontext.';
        }

        if (!file_exists($filePath)) {
            return "// [System Error] File not found at path: {$filePath}";
        }

        if (!is_readable($filePath)) {
            return "// [System Error] File found but not readable: {$filePath}";
        }

        return file_get_contents($filePath);
    }

    /**
     * Beautifully formats JSON for insertion into prompt
     */
    private function renderContextJSON(): string
    {
        return json_encode($this->analysisResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}