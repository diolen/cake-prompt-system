<?php

namespace App\Generator;

use App\Generator\Templates\RefactorTemplate;
use App\Generator\Templates\FeatureTemplate;
use App\Generator\Templates\DebugTemplate;
use App\Generator\Templates\ViewTemplate;
use Exception;

class PromptGenerator
{
    /**
     * Generates ready prompt based on analysis, task type and custom instruction
     */
    public function generate(array $analysisResult, string $taskType, string $customInstruction = ''): string
    {
        $targetLayer = $analysisResult['task_context']['target_layer'] ?? 'Unknown';
        $taskTypeUpper = strtoupper($taskType);

        // Use ViewTemplate for View layer regardless of task type
        if ($targetLayer === 'View') {
            $template = new ViewTemplate($analysisResult);
        } else {
            switch ($taskTypeUpper) {
                case 'REFACTOR':
                    $template = new RefactorTemplate($analysisResult);
                    break;
                case 'FEATURE':
                    $template = new FeatureTemplate($analysisResult);
                    break;
                case 'DEBUG':
                    $template = new DebugTemplate($analysisResult);
                    break;
                default:
                    throw new Exception("Unknown task type: {$taskType}");
            }
        }

        // Pass user instruction to base template compiler
        return $template->compile($customInstruction);
    }
}