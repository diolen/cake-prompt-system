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
            throw new Exception("Critical error: JSON schema file not found at path: {$schemaPath}");
        }
        $this->schemaPath = $schemaPath;
    }

    /**
     * Validates data array against JSON schema
     * @throws Exception if data is invalid
     */
    public function validate(array $data): void
    {
        // The justinrainbow/json-schema library requires an object (stdClass), not an associative array
        $dataObject = json_decode(json_encode($data));

        $validator = new Validator();
        $validator->validate($dataObject, (object)['$ref' => 'file://' . realpath($this->schemaPath)]);

        if (!$validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf("[%s] %s", $error['property'], $error['message']);
            }
            
            throw new Exception("JSON schema validation error:\n" . implode("\n", $errors));
        }
    }
}