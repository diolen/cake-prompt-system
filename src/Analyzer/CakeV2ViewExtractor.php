<?php

namespace App\Analyzer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class CakeV2ViewExtractor extends NodeVisitorAbstract
{
    private array $helpers = [];
    private array $elements = [];
    private array $variables = [];
    private string $controllerName = '';
    private ?string $projectRoot = null;

    public function __construct(string $filePath, ?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot;

        // Extract controller name from path: /sic/app/View/ControllerName/action.ctp
        if (preg_match('/\/View\/([^\/]+)\//', $filePath, $matches)) {
            $this->controllerName = $matches[1];
        }

        // Fallback: extract elements using regex for .ctp files
        $this->extractElementsFromCode(file_get_contents($filePath));
    }

    /**
     * Extract elements using regex as fallback for .ctp files
     */
    private function extractElementsFromCode(string $code): void
    {
        // Match $this->element('element_name') patterns
        if (preg_match_all('/\$this->element\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $code, $matches)) {
            foreach ($matches[1] as $elementName) {
                if (!in_array($elementName, $this->elements)) {
                    $this->elements[] = $elementName;
                }
            }
        }
    }

    /**
     * AST node traversal logic
     */
    public function leaveNode(Node $node)
    {
        // Handle echo statements that might contain method calls
        if ($node instanceof Node\Stmt\Echo_) {
            foreach ($node->exprs as $expr) {
                if ($expr instanceof Node\Expr\MethodCall) {
                    $this->processMethodCall($expr);
                }
            }
        }

        // Extract helper calls: $this->Html->link(), $this->Form->input(), etc.
        if ($node instanceof Node\Expr\MethodCall) {
            $this->processMethodCall($node);
        }

        // Extract variable usage: $items, $user, etc.
        if ($node instanceof Node\Expr\Variable) {
            $varName = $node->name;
            if (is_string($varName) && $varName !== 'this' && !in_array($varName, $this->variables)) {
                // Exclude superglobals
                $superglobals = ['_GET', '_POST', '_SERVER', '_REQUEST', '_SESSION', '_COOKIE', '_FILES', '_ENV'];
                if (!in_array($varName, $superglobals)) {
                    $this->variables[] = $varName;
                }
            }
        }
    }

    /**
     * Process method call nodes to extract helpers and elements
     */
    private function processMethodCall(Node\Expr\MethodCall $node)
    {
        // Extract helper calls: $this->Html->link(), $this->Form->input(), etc.
        if ($node->var instanceof Node\Expr\PropertyFetch) {
            if ($node->var->var instanceof Node\Expr\Variable && $node->var->var->name === 'this') {
                $helperName = $node->var->name->toString();
                $methodName = $node->name->toString();

                // Check if it's an element call
                if ($methodName === 'element') {
                    if (!empty($node->args)) {
                        $arg = $node->args[0];
                        // Handle both Arg and direct value nodes
                        if ($arg instanceof Node\Arg) {
                            $value = $arg->value;
                        } else {
                            $value = $arg;
                        }

                        if ($value instanceof Node\Scalar\String_) {
                            $elementName = $value->value;
                            if (!in_array($elementName, $this->elements)) {
                                $this->elements[] = $elementName;
                            }
                        }
                    }
                } else {
                    // It's a helper call
                    if (!in_array($helperName, $this->helpers)) {
                        $this->helpers[] = $helperName;
                    }
                }
            }
        }
    }

    /**
     * Get collected helpers
     */
    public function getHelpers(): array
    {
        return array_unique($this->helpers);
    }

    /**
     * Get collected elements
     */
    public function getElements(): array
    {
        return array_unique($this->elements);
    }

    /**
     * Get collected variables
     */
    public function getVariables(): array
    {
        return array_unique($this->variables);
    }

    /**
     * Get controller name
     */
    public function getControllerName(): string
    {
        return $this->controllerName;
    }

    /**
     * Get element context (deep analysis of element files)
     */
    public function getElementContext(): array
    {
        $elementContext = [];
        $projectRoot = $this->findProjectRoot();

        foreach ($this->elements as $elementName) {
            $elementPath = $this->findElementPath($elementName, $projectRoot);
            if ($elementPath && file_exists($elementPath)) {
                $elementCode = file_get_contents($elementPath);
                $elementContext[$elementName] = $this->analyzeElementCode($elementCode);
            }
        }

        return $elementContext;
    }

    /**
     * Find project root from file path
     */
    private function findProjectRoot(): ?string
    {
        if ($this->projectRoot) {
            return $this->projectRoot;
        }
        return null;
    }

    /**
     * Find element file path
     */
    private function findElementPath(string $elementName, ?string $projectRoot): ?string
    {
        if (!$projectRoot) {
            return null;
        }

        $possiblePaths = [
            $projectRoot . '/app/View/Elements/' . $elementName . '.ctp',
            $projectRoot . '/View/Elements/' . $elementName . '.ctp',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Analyze element code to extract helpers and variables
     */
    private function analyzeElementCode(string $code): array
    {
        $helpers = [];
        $variables = [];
        $nestedElements = [];

        // Extract helpers using regex
        if (preg_match_all('/\$this->([A-Z][a-zA-Z]+)->/', $code, $matches)) {
            $helpers = array_unique($matches[1]);
        }

        // Extract variables
        if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $code, $matches)) {
            $superglobals = ['_GET', '_POST', '_SERVER', '_REQUEST', '_SESSION', '_COOKIE', '_FILES', '_ENV', 'this'];
            $variables = array_filter(array_unique($matches[1]), function($var) use ($superglobals) {
                return !in_array($var, $superglobals);
            });
        }

        // Extract nested elements
        if (preg_match_all('/\$this->element\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $code, $matches)) {
            $nestedElements = array_unique($matches[1]);
        }

        return [
            'helpers' => array_values($helpers),
            'variables' => array_values($variables),
            'nested_elements' => array_values($nestedElements)
        ];
    }
}
