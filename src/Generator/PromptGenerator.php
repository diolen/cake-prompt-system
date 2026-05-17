<?php

namespace App\Generator;

use App\Generator\Templates\RefactorTemplate;
use App\Generator\Templates\FeatureTemplate;
use App\Generator\Templates\DebugTemplate;
use Exception;

class PromptGenerator
{
    /**
     * Генерирует готовый промпт на основе анализа и типа задачи
     */
    public function generate(array $analysisResult, string $taskType): string
    {
        switch (strtoupper($taskType)) {
            case 'REFACTOR':
                $template = new RefactorTemplate($analysisResult);
                break;
            case 'FEATURE':
                $template = new FeatureTemplate($analysisResult);
                break;
            case 'DEBUG':
                $template = new DebugTemplate($analysisResult); // НОВОЕ!
                break;
            default:
                throw new Exception("Неизвестный тип задачи: {$taskType}");
        }

        return $template->compile();
    }
}