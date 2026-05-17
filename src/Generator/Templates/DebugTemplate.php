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
            $warningNotice = "⚠️ ВНИМАНИЕ: Вы исправляете баг в высоконагруженном по связям компоненте ($influence). Изменения затронут или могут сломать: $componentsList. Исправление багов не должно менять сигнатуры методов или возвращаемые типы данных, если это жестко не требуется для устранения дефекта.\n";
        }

        return <<<INSTRUCTION
Найдите и устраните баг или уязвимость в целевом файле.
$warningNotice
Правила отладки и исправления ошибок:
1. Защита от Null: В PHP 5.6 нет Nullsafe-оператора (?->). Обязательно добавляйте явные проверки if (is_object(\$var)) или if (!empty(\$var)) перед вызовом методов смежных моделей или компонентов.
2. Логирование CakePHP v2: При необходимости логирования ошибок используйте CakeLog::write('debug', 'сообщение') или \$this->log('сообщение', 'debug'). Запрещено использовать современные методы логирования PSR-3.
3. Обработка исключений: Помните, что в PHP 5.6 базовый интерфейс \Throwable отсутствует, а большинство внутренних ошибок PHP не перехватываются через try/catch (Exception). Убедитесь, что логика валидации предотвращает фатальные ошибки до их возникновения.
INSTRUCTION;
    }
}