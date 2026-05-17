<?php

namespace App\Analyzer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

class CakeV2PropertyExtractor extends NodeVisitorAbstract
{
    private array $models = [];
    private array $components = [];
    private array $associations = [];

    public function leaveNode(Node $node)
    {
        // 1. Извлечение статических свойств класса (уровень свойств контроллера/модели)
        if ($node instanceof Property) {
            foreach ($node->props as $prop) {
                $propertyName = $prop->name->toString();
                
                // Извлечение данных для Контроллеров (uses, components)
                if (in_array($propertyName, ['uses', 'components'])) {
                    $values = $this->extractArrayValues($prop->default);
                    if ($propertyName === 'uses') {
                        $this->models = array_merge($this->models, $values);
                    } elseif ($propertyName === 'components') {
                        $this->components = array_merge($this->components, $values);
                    }
                }
                
                // Извлечение данных для Моделей (CakePHP v2 associations)
                if (in_array($propertyName, ['belongsTo', 'hasMany', 'hasOne', 'hasAndBelongsToMany'])) {
                    $associatedWith = $this->extractAssociationKeys($prop->default);
                    foreach ($associatedWith as $modelName) {
                        $this->associations[] = [
                            'type' => $propertyName,
                            'model' => $modelName
                        ];
                    }
                }
            }
        }

        // 2. Новая логика: Сбор динамических моделей через $this->loadModel('ModelName') внутри методов
        if ($node instanceof MethodCall) {
            // Проверяем, что вызов идет у объекта $this
            if ($node->var instanceof Variable && $node->var->name === 'this') {
                // Проверяем, что имя метода именно loadModel
                if ($node->name instanceof Identifier && $node->name->toString() === 'loadModel') {
                    // Проверяем, что передан первый аргумент и это строка
                    if (isset($node->args[0]) && $node->args[0]->value instanceof String_) {
                        $dynamicModel = $node->args[0]->value->value;
                        $this->models[] = $dynamicModel;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Помощник для извлечения простых значений из индексного массива
     */
    private function extractArrayValues(?Node $expr): array
    {
        $values = [];
        if ($expr instanceof Array_) {
            foreach ($expr->items as $item) {
                if ($item !== null && $item->value instanceof String_) {
                    $values[] = $item->value->value;
                }
            }
        }
        return $values;
    }

    /**
     * Помощник для извлечения ассоциированных моделей.
     * В CakePHP ассоциации могут быть заданы как простым массивом array('Group'),
     * так и ассоциативным array('Group' => array('className' => 'Group')).
     * Нам в обоих случаях нужны ключи/названия моделей.
     */
    private function extractAssociationKeys(?Node $expr): array
    {
        $keys = [];
        if ($expr instanceof Array_) {
            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }
                
                // Если задано как 'Group' => array(...)
                if ($item->key instanceof String_) {
                    $keys[] = $item->key->value;
                } 
                // Если задано просто как значение в массиве array('Group')
                elseif ($item->value instanceof String_) {
                    $keys[] = $item->value->value;
                }
            }
        }
        return $keys;
    }

    public function getModels(): array
    {
        return array_values(array_unique($this->models));
    }

    public function getComponents(): array
    {
        return array_values(array_unique($this->components));
    }

    public function getAssociations(): array
    {
        return $this->associations;
    }

    /**
     * Сброс внутреннего состояния экстрактора перед парсингом нового файла
     */
    public function clear(): void
    {
        $this->models = [];
        $this->components = [];
        $this->associations = [];
    }

    /**
     * Возвращает плоский список всех сущностей, от которых зависит данный файл,
     * для построения глобального индекса связей.
     */
    public function extractDependencies(): array
    {
        $flat = array_merge($this->models, $this->components);
        foreach ($this->associations as $assoc) {
            $flat[] = $assoc['model'];
        }
        return array_values(array_unique($flat));
    }
}