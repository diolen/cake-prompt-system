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
     * AST node traversal logic
     */
    public function leaveNode(Node $node)
    {
        // Search for properties inside class (e.g.: public \$belongsTo = array(...);)
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
     * Parse CakePHP array structure (supports both explicit arrays and simplified strings)
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

            // Case 1: Simplified CakePHP notation: public \$hasMany = array('Order');
            // No key, only string value
            if ($item->key === null && $item->value instanceof Node\Scalar\String_) {
                $alias = $item->value->value;
                $result[$alias] = [
                    'className'  => $alias,
                    'foreignKey' => $this->guessForeignKey($alias),
                    'dependent'  => false
                ];
                continue;
            }

            // Case 2: Standard notation: 'Order' => array('className' => 'Order', ...)
            if ($item->key instanceof Node\Scalar\String_) {
                $alias = $item->key->value;
                $assocConfig = [];

                if ($item->value instanceof Node\Expr\Array_) {
                    foreach ($item->value->items as $subItem) {
                        if ($subItem !== null && $subItem->key instanceof Node\Scalar\String_) {
                            $paramName = $subItem->key->value;
                            
                            // Extract only string and boolean configuration values
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
     * Fallback for generating foreign key by CakePHP conventions (User -> user_id)
     */
    private function guessForeignKey(string $alias): string
    {
        // Basic snake_case converter to follow CakePHP v2 conventions
        $underscored = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $alias));
        return $underscored . '_id';
    }

    /**
     * Get collected model associations
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }
}