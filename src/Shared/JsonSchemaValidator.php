<?php

namespace App\Shared;

use JsonSchema\Validator;
use Exception;

class JsonSchemaValidator
{
    private string $schemaPath;

    public function __construct(string $schemaPath)
    {
        if (!file_exists($schemaPath)) {
            throw new Exception("Критическая ошибка: Файл JSON-схемы не найден по пути: {$schemaPath}");
        }
        $this->schemaPath = $schemaPath;
    }

    /**
     * Валидирует массив данных против JSON-схемы
     * @throws Exception если данные не валидны
     */
    public function validate(array $data): void
    {
        // Библиотека justinrainbow/json-schema требует объект (stdClass), а не ассоциативный массив
        $dataObject = json_decode(json_encode($data));

        $validator = new Validator();
        $validator->validate($dataObject, (object)['$ref' => 'file://' . realpath($this->schemaPath)]);

        if (!$validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf("[%s] %s", $error['property'], $error['message']);
            }
            
            throw new Exception("Ошибка валидации JSON-схемы:\n" . implode("\n", $errors));
        }
    }
}