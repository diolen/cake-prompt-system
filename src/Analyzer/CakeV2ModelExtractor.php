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

    private array $validate = [];

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

                if ($propName === 'validate') {
                    $this->validate = $this->parseValidateArray($prop->default);
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
     * Parse CakePHP validation array structure
     */
    private function parseValidateArray(?Node $arrayNode): array
    {
        if (!$arrayNode instanceof Node\Expr\Array_) {
            return [];
        }

        $result = [];
        foreach ($arrayNode->items as $item) {
            if ($item === null) {
                continue;
            }

            // Field name as key, validation rules as value
            if ($item->key instanceof Node\Scalar\String_) {
                $fieldName = $item->key->value;
                $rules = [];

                if ($item->value instanceof Node\Expr\Array_) {
                    $currentRuleSet = [];
                    
                    foreach ($item->value->items as $ruleItem) {
                        if ($ruleItem === null) {
                            continue;
                        }

                        // Case 1: Standard format: 'rule' => 'notEmpty', 'message' => '...'
                        if ($ruleItem->key instanceof Node\Scalar\String_) {
                            $paramName = $ruleItem->key->value;
                            $paramValue = $this->extractNodeValue($ruleItem->value);

                            if ($paramValue !== null) {
                                // Check if this is a nested rule (alternative CakePHP format)
                                // e.g., 'required' => array('rule' => 'notEmpty')
                                if ($ruleItem->value instanceof Node\Expr\Array_) {
                                    // Flush any accumulated standard rule set first
                                    if (!empty($currentRuleSet)) {
                                        $rules[] = $currentRuleSet;
                                        $currentRuleSet = [];
                                    }
                                    
                                    $nestedRule = [];
                                    foreach ($ruleItem->value->items as $nestedItem) {
                                        if ($nestedItem !== null && $nestedItem->key instanceof Node\Scalar\String_) {
                                            $nestedParamName = $nestedItem->key->value;
                                            $nestedParamValue = $this->extractNodeValue($nestedItem->value);
                                            if ($nestedParamValue !== null) {
                                                $nestedRule[$nestedParamName] = $nestedParamValue;
                                            }
                                        }
                                    }
                                    if (!empty($nestedRule)) {
                                        // Add rule name as 'rule' parameter if not present
                                        if (!isset($nestedRule['rule'])) {
                                            $nestedRule['rule'] = $paramName;
                                        }
                                        $rules[] = $nestedRule;
                                    }
                                } else {
                                    // Standard parameter (rule, message, required, etc.)
                                    $currentRuleSet[$paramName] = $paramValue;
                                }
                            }
                        }
                        // Case 2: Multiple rule sets without keys: array(array('rule' => '...'), array('rule' => '...'))
                        elseif ($ruleItem->key === null && $ruleItem->value instanceof Node\Expr\Array_) {
                            // Flush any accumulated standard rule set first
                            if (!empty($currentRuleSet)) {
                                $rules[] = $currentRuleSet;
                                $currentRuleSet = [];
                            }
                            
                            $ruleSet = [];
                            foreach ($ruleItem->value->items as $subItem) {
                                if ($subItem !== null && $subItem->key instanceof Node\Scalar\String_) {
                                    $paramName = $subItem->key->value;
                                    $paramValue = $this->extractNodeValue($subItem->value);
                                    if ($paramValue !== null) {
                                        $ruleSet[$paramName] = $paramValue;
                                    }
                                }
                            }
                            if (!empty($ruleSet)) {
                                $rules[] = $ruleSet;
                            }
                        }
                    }
                    
                    // Flush any remaining standard rule set
                    if (!empty($currentRuleSet)) {
                        $rules[] = $currentRuleSet;
                    }
                }

                if (!empty($rules)) {
                    $result[$fieldName] = $rules;
                }
            }
        }

        return $result;
    }

    /**
     * Extract value from AST node (supports strings, numbers, booleans, arrays)
     */
    private function extractNodeValue(?Node $node)
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        if ($node instanceof Node\Scalar\Int_) {
            return $node->value;
        }

        if ($node instanceof Node\Scalar\Float_) {
            return $node->value;
        }

        if ($node instanceof Node\Expr\ConstFetch) {
            $constName = strtolower($node->name->toString());
            if ($constName === 'true') {
                return true;
            }
            if ($constName === 'false') {
                return false;
            }
        }

        if ($node instanceof Node\Expr\Array_) {
            $array = [];
            foreach ($node->items as $item) {
                if ($item === null) {
                    continue;
                }

                $key = $item->key instanceof Node\Scalar\String_ ? $item->key->value : null;
                $value = $this->extractNodeValue($item->value);

                if ($key !== null) {
                    $array[$key] = $value;
                } else {
                    $array[] = $value;
                }
            }
            return $array;
        }

        return null;
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

    /**
     * Get collected validation rules
     */
    public function getValidate(): array
    {
        return $this->validate;
    }
}