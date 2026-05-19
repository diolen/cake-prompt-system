<?php

namespace App\Generator\Templates;

use App\Generator\AbstractPromptTemplate;

class DebugTemplate extends AbstractPromptTemplate
{
    public function getTaskInstruction(): string
    {
        $metrics = $this->analysisResult['metrics'] ?? [];
        $influence = $metrics['influence_score'] ?? 0;
        $impactedBy = $this->analysisResult['impacted_by'] ?? [];

        $warningNotice = '';
        if ($influence > 0) {
            $componentsList = implode(', ', $impactedBy);
            $warningNotice = "⚠️ WARNING: You are fixing a bug in a highly connected component ($influence). Changes will affect or may break: $componentsList. Bug fixes should not change method signatures or return data types unless strictly required to fix the defect.\n";
        }

        return <<<INSTRUCTION
Find and fix a bug or vulnerability in the target file.
$warningNotice
Debugging and error fixing rules:
1. Null Protection: PHP 5.6 has no Nullsafe operator (?->). Always add explicit checks if (is_object(\$var)) or if (!empty(\$var)) before calling methods on related models or components.
2. CakePHP v2 Logging: When error logging is needed, use CakeLog::write('debug', 'message') or \$this->log('message', 'debug'). Modern PSR-3 logging methods are prohibited.
3. Exception Handling: Remember that PHP 5.6 lacks the base \Throwable interface, and most internal PHP errors are not caught by try/catch (Exception). Ensure validation logic prevents fatal errors before they occur.
INSTRUCTION;
    }
}