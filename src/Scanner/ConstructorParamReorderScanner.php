<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Scanner;

use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use PhpParser\Parser;

final class ConstructorParamReorderScanner implements ScannerInterface
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function scan(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return [];
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (\PhpParser\Error) {
            return [];
        }
        if ($ast === null) {
            return [];
        }

        $findings = [];
        $classes = $this->collectClasses($ast);
        foreach ($classes as $class) {
            $className = $this->resolveClassName($class);
            $method = $class->getMethod('__construct');
            if ($method === null) {
                continue;
            }

            $paramList = $this->buildParamList($method);
            if ($paramList === []) {
                continue;
            }

            if (!$this->hasReorderIssue($method)) {
                continue;
            }

            $findings[] = new Finding(
                ruleId: 'constructor-param-reorder',
                severity: 'warning',
                file: $filePath,
                line: $method->getStartLine(),
                description: 'Constructor has required typed parameter after optional parameter — PHP 8.x will throw',
                context: [
                    'params' => $paramList,
                ],
                className: $className,
                phpVersion: '8.x',
            );
        }

        return $findings;
    }

    /**
     * @param Node[] $ast
     * @return Class_[]
     */
    private function collectClasses(array $ast): array
    {
        $classes = [];
        foreach ($ast as $node) {
            if ($node instanceof Class_) {
                if (!$node->isAnonymous()) {
                    $classes[] = $node;
                }
            } elseif ($node instanceof Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Class_ && !$stmt->isAnonymous()) {
                        $classes[] = $stmt;
                    }
                }
            }
        }
        return $classes;
    }

    private function hasReorderIssue(ClassMethod $method): bool
    {
        $hasOptional = false;
        foreach ($method->getParams() as $param) {
            if ($param->default !== null) {
                $hasOptional = true;
                continue;
            }

            if ($hasOptional && $this->isTypedRequiredParam($param)) {
                return true;
            }
        }

        return false;
    }

    private function isTypedRequiredParam(Param $param): bool
    {
        if ($param->default !== null) {
            return false;
        }

        return $param->type !== null;
    }

    /**
     * @return array<int, array{type: string|null, name: string, default: string|null}>
     */
    private function buildParamList(ClassMethod $method): array
    {
        $params = [];
        foreach ($method->getParams() as $param) {
            $typeStr = $this->paramTypeToString($param);
            $nameStr = '?';
            if ($param->var instanceof \PhpParser\Node\Expr\Variable) {
                if (is_string($param->var->name)) {
                    $nameStr = '$' . $param->var->name;
                } elseif ($param->var->name instanceof Identifier) {
                    $nameStr = '$' . $param->var->name->toString();
                }
            }
            $defaultStr = $this->paramDefaultToString($param);

            $params[] = [
                'type' => $typeStr,
                'name' => $nameStr,
                'default' => $defaultStr,
            ];
        }

        return $params;
    }

    private function paramTypeToString(Param $param): ?string
    {
        if ($param->type === null) {
            return null;
        }

        if ($param->type instanceof Name) {
            return $param->type->toString();
        }

        if ($param->type instanceof Identifier) {
            return $param->type->toString();
        }

        if ($param->type instanceof \PhpParser\Node\NullableType) {
            $inner = $param->type->type;
            if ($inner instanceof Name) {
                return '?' . $inner->toString();
            }
            if ($inner instanceof Identifier) {
                return '?' . $inner->toString();
            }
        }

        if ($param->type instanceof \PhpParser\Node\UnionType) {
            $parts = [];
            foreach ($param->type->types as $t) {
                if ($t instanceof Name) {
                    $parts[] = $t->toString();
                } elseif ($t instanceof Identifier) {
                    $parts[] = $t->toString();
                }
            }
            return implode('|', $parts);
        }

        return null;
    }

    private function paramDefaultToString(Param $param): ?string
    {
        if ($param->default === null) {
            return null;
        }

        if ($param->default instanceof \PhpParser\Node\Expr\ConstFetch) {
            return $param->default->name->toString();
        }

        if ($param->default instanceof \PhpParser\Node\Scalar\String_) {
            return "'" . $param->default->value . "'";
        }

        if ($param->default instanceof \PhpParser\Node\Scalar\LNumber) {
            return (string) $param->default->value;
        }

        if ($param->default instanceof \PhpParser\Node\Expr\Array_) {
            return '[]';
        }

        if ($param->default instanceof \PhpParser\Node\Expr\New_) {
            return 'new ' . ($param->default->class instanceof Name ? $param->default->class->toString() : '?');
        }

        return '...';
    }

    private function resolveClassName(Class_ $class): string
    {
        if ($class->name instanceof Identifier) {
            return $class->name->toString();
        }
        return 'anonymous';
    }
}
