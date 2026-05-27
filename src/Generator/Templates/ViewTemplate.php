<?php

namespace App\Generator\Templates;

use App\Generator\AbstractPromptTemplate;

class ViewTemplate extends AbstractPromptTemplate
{
    public function getTaskInstruction(): string
    {
        return <<<INSTRUCTION
Develop and implement changes to the View layer (.ctp template file) in the target file.

View implementation rules in CakePHP v2:
1. Helper Usage: Use CakePHP helpers for HTML generation instead of raw HTML tags:
   - \$this->Html->link() for links
   - \$this->Form->create(), \$this->Form->input(), \$this->Form->end() for forms
   - \$this->Paginator->sort(), \$this->Paginator->numbers() for pagination
   - \$this->Session->flash() for flash messages
2. Data Display: Display data passed from Controller using the \$this->data array or view variables (e.g., \$items, \$user).
3. Elements: Reuse view components using \$this->element('element_name') for common UI patterns.
4. Security: Always escape user input using helpers (they auto-escape by default). Never output raw user data.
5. PHP 5.6 Compatibility: Use array() syntax instead of short array []. Avoid null coalescing operator ??.
6. JavaScript/jQuery: If JavaScript is needed, wrap in document ready: \$(function(){ ... });
7. CSS Classes: Use Bootstrap classes if present in the project (e.g., btn, table, form-control).
INSTRUCTION;
    }
}
