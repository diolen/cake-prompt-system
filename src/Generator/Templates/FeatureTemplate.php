<?php

namespace App\Generator\Templates;

use App\Generator\AbstractPromptTemplate;

class FeatureTemplate extends AbstractPromptTemplate
{
    public function getTaskInstruction(): string
    {
        $metrics = $this->analysisResult['metrics'] ?? [];
        $influence = $metrics['influence_score'] ?? 0;
        $impactedBy = $this->analysisResult['impacted_by'] ?? [];

        $warningNotice = '';
        if ($influence > 0) {
            $componentsList = implode(', ', $impactedBy);
            $warningNotice = "⚠️ WARNING: Changes are being made to a component with high Influence Score ($influence). The following depend on it: $componentsList. Implement new methods to completely eliminate side effects for dependent classes. Use optional arguments with default values when necessary.\n";
        }

        return <<<INSTRUCTION
Develop and implement a new feature (new methods/logic) in the target file.
$warningNotice
Feature implementation rules in CakePHP v2:
1. Architectural Separation: Follow the "Fat Model, Skinny Controller" paradigm. All business logic, data manipulation, and validation should be in the Model. The Controller should only coordinate data flow.
2. Database Operations: Writing raw SQL queries in controllers is prohibited. Use CakePHP ORM methods (\$this->Model->find(), \$this->Model->save(), etc.).
3. Using Relationships: Pay attention to available Relations in context (models, components, associations arrays). Use them to call methods on related entities instead of duplicating code.
INSTRUCTION;
    }
}