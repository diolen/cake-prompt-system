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
            $warningNotice = "⚠️ ВНИМАНИЕ: Изменения вносятся в компонент с высоким Influence Score ($influence). От него зависят: $componentsList. Реализуйте новые методы так, чтобы полностью исключить сайд-эффекты для зависимых классов. При необходимости используйте опциональные аргументы со значениями по умолчанию.\n";
        }

        return <<<INSTRUCTION
Разработайте и внедрите новую фичу (новые методы/логику) в целевой файл.
$warningNotice
Правила реализации фичи в CakePHP v2:
1. Архитектурное разделение: Соблюдайте парадигму "Fat Model, Skinny Controller". Вся бизнес-логика, манипуляции с данными и валидация должны находиться в Модели. Контроллер должен лишь координировать поток данных.
2. Работа с БД: Запрещено писать сырые SQL-запросы в контроллерах. Используйте методыORM CakePHP (\$this->Model->find(), \$this->Model->save() и т.д.).
3. Использование связей: Обратите внимание на доступныеRelations в контексте (массивы models, components, associations). Используйте их для вызова методов смежных сущностей вместо дублирования кода.
INSTRUCTION;
    }
}