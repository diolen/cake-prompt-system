<?php

namespace App\Generator\Templates;
use App\Generator\AbstractPromptTemplate;

class RefactorTemplate extends AbstractPromptTemplate
{
    public function getTaskInstruction(): string
    {
        $metrics = $this->analysisResult['metrics'] ?? [];
        $influence = $metrics['influence_score'] ?? 0;
        $impactedBy = $this->analysisResult['impacted_by'] ?? [];

        $warningNotice = '';
        if ($influence > 0) {
            $componentsList = implode(', ', $impactedBy);
            $warningNotice = "⚠️ WARNING: This component has a high Influence Score ($influence). The following depend on it: $componentsList. Changes must not break their public interface! Act as conservatively as possible.\n";
        }

        return <<<INSTRUCTION
Perform refactoring of the target file.
$warningNotice
Refactoring goals:
1. Optimize legacy code without breaking backward compatibility.
2. Fix potential vulnerabilities or suboptimal queries if found.
3. Follow CakePHP v2 conventions (using models via \$this->{ModelName}, proper component handling).
INSTRUCTION;
    }
}