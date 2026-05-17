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
            $warningNotice = "⚠️ ВНИМАНИЕ: Этот компонент имеет высокий Influence Score ($influence). От него зависят: $componentsList. Изменения не должны сломать их публичный интерфейс! Действуйте максимально консервативно.\n";
        }

        return <<<INSTRUCTION
Выполните рефакторинг целевого файла.
$warningNotice
Цели рефакторинга:
1. Оптимизация legacy-кода без нарушения обратной совместимости.
2. Исправление потенциальных уязвимостей или неоптимальных запросов, если они обнаружены.
3. Соблюдение конвенций CakePHP v2 (использование моделей через \$this->{ModelName}, правильная работа с компонентами).
INSTRUCTION;
    }
}