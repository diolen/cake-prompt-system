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
        // 1. Extract static class properties (controller/model property level)
        if ($node instanceof Property) {
            foreach ($node->props as $prop) {
                $propertyName = $prop->name->toString();
                
                // Extract data for Controllers (uses, components)
                if (in_array($propertyName, ['uses', 'components'])) {
                    $values = $this->extractArrayValues($prop->default);
                    if ($propertyName === 'uses') {
                        $this->models = array_merge($this->models, $values);
                    } elseif ($propertyName === 'components') {
                        $this->components = array_merge($this->components, $values);
                    }
                }
                
                // Extract data for Models (CakePHP v2 associations)
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

        // 2. New logic: Collect dynamic models via $this->loadModel('ModelName') inside methods
        if ($node instanceof MethodCall) {
            // Check that the call is on $this object
            if ($node->var instanceof Variable && $node->var->name === 'this') {
                // Check that method name is loadModel
                if ($node->name instanceof Identifier && $node->name->toString() === 'loadModel') {
                    // Check that first argument is passed and it's a string
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
     * Helper for extracting simple values from indexed array
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
     * Helper for extracting associated models.
     * In CakePHP, associations can be specified as simple array array('Group'),
     * or associative array('Group' => array('className' => 'Group')).
     * We need keys/model names in both cases.
     */
    private function extractAssociationKeys(?Node $expr): array
    {
        $keys = [];
        if ($expr instanceof Array_) {
            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }
                
                // If specified as 'Group' => array(...)
                if ($item->key instanceof String_) {
                    $keys[] = $item->key->value;
                } 
                // If specified simply as value in array array('Group')
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
     * Reset extractor internal state before parsing new file
     */
    public function clear(): void
    {
        $this->models = [];
        $this->components = [];
        $this->associations = [];
    }

    /**
     * Returns flat list of all entities this file depends on,
     * for building global relationship index.
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