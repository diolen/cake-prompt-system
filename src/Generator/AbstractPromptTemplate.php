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
        $template = <<<'PROMPT'
# СИСТЕМНЫЕ ОГРАНИЧЕНИЯ ОКРУЖЕНИЯ (КРИТИЧЕСКИ ВАЖНО)
Вы — эксперт по legacy-разработке на PHP и фреймворку CakePHP v2.x.
Весь предоставляемый вами код ДОЛЖЕН строго соответствовать стандартам **PHP 5.6**.

## Жесткие синтаксические запреты PHP 5.6:
1. ЗАПРЕЩЕНО использовать короткий синтаксис массивов `[]`. Используйте только `array()`.
2. ЗАПРЕЩЕН Null Coalescing оператор `??`. Используйте `isset($var) ? $var : $default`.
3. ЗАПРЕЩЕНЫ стрелочные функции `fn() =>`. Используйте `function() use () {}`.
4. ЗАПРЕЩЕНЫ typed properties (модификаторы типов свойств класса, например: private $name;). Пишите просто private $name;.
5. ЗАПРЕЩЕНЫ return types и argument types для скалярных типов (string, int, bool) в сигнатурах методов.

---

# КОНТЕКСТ АНАЛИЗИРУЕМОГО КОМПОНЕНТА
Ниже приведены данные статического анализа файла, который необходимо обработать:
{CONTEXT_JSON}

---

# ИНСТРУКЦИЯ К ВЫПОЛНЕНИЮ ЗАДАЧИ
{TASK_INSTRUCTION}

ОТВЕТЬТЕ ТОЛЬКО КОДОМ И КРАТКИМ ОПИСАНИЕМ ИЗМЕНЕНИЙ НА РУССКОМ ЯЗЫКЕ.
PROMPT;

        // Безопасно подставляем динамический контекст без варнингов парсера
        $compiled = str_replace('{CONTEXT_JSON}', $this->renderContextJSON(), $template);
        $compiled = str_replace('{TASK_INSTRUCTION}', $this->getTaskInstruction(), $compiled);

        return $compiled;
    }

    /**
     * Красиво форматирует JSON для вставки в промпт
     */
    private function renderContextJSON(): string
    {
        return json_encode($this->analysisResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}