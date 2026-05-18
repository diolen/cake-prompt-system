<?php

namespace App\Analyzer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class CakeV2ModelExtractor extends NodeVisitorAbstract
{
    private array $associations = [
        'belongsTo' => [],
        'hasMany' => [],
        'hasOne' => [],
        'hasAndBelongsToMany' => []
    ];

    /**
     * Логика обхода узлов AST
     */
    public function leaveNode(Node $node)
    {
        // Ищем свойства внутри класса (например: public \$belongsTo = array(...);)
        if ($node instanceof Node\Stmt\Property) {
            foreach ($node->props as $prop) {
                $propName = $prop->name->toString();

                if (array_key_exists($propName, $this->associations)) {
                    $this->associations[$propName] = $this->parseAssociationArray($prop->default);
                }
            }
        }
    }

    /**
     * Разбор структуры массива CakePHP (поддерживает как явные массивы, так и упрощенные строки)
     */
    private function parseAssociationArray(?Node $arrayNode): array
    {
        if (!$arrayNode instanceof Node\Expr\Array_) {
            return [];
        }

        $result = [];
        foreach ($arrayNode->items as $item) {
            if ($item === null) {
                continue;
            }

            // Случай 1: Упрощенная нотация CakePHP: public \$hasMany = array('Order');
            // Ключа нет, есть только строковое значение
            if ($item->key === null && $item->value instanceof Node\Scalar\String_) {
                $alias = $item->value->value;
                $result[$alias] = [
                    'className'  => $alias,
                    'foreignKey' => $this->guessForeignKey($alias),
                    'dependent'  => false
                ];
                continue;
            }

            // Случай 2: Стандартная нотация: 'Order' => array('className' => 'Order', ...)
            if ($item->key instanceof Node\Scalar\String_) {
                $alias = $item->key->value;
                $assocConfig = [];

                if ($item->value instanceof Node\Expr\Array_) {
                    foreach ($item->value->items as $subItem) {
                        if ($subItem !== null && $subItem->key instanceof Node\Scalar\String_) {
                            $paramName = $subItem->key->value;
                            
                            // Извлекаем только строковые и булевые значения конфигурации
                            if ($subItem->value instanceof Node\Scalar\String_) {
                                $assocConfig[$paramName] = $subItem->value->value;
                            } elseif ($subItem->value instanceof Node\Expr\ConstFetch) {
                                $constName = strtolower($subItem->value->name->toString());
                                if ($constName === 'true' || $constName === 'false') {
                                    $assocConfig[$paramName] = ($constName === 'true');
                                }
                            }
                        }
                    }
                }

                $result[$alias] = [
                    'className'  => $assocConfig['className'] ?? $alias,
                    'foreignKey' => $assocConfig['foreignKey'] ?? $this->guessForeignKey($alias),
                    'dependent'  => $assocConfig['dependent'] ?? false
                ];
            }
        }

        return $result;
    }

    /**
     * Фолбэк генерации внешнего ключа по конвенциям CakePHP (User -> user_id)
     */
    private function guessForeignKey(string $alias): string
    {
        // Базовый snake_case конвертер для соблюдения конвенций CakePHP v2
        $underscored = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $alias));
        return $underscored . '_id';
    }

    /**
     * Получить собранные ассоциации модели
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }
}