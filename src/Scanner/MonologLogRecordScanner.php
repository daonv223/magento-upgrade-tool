<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Scanner;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Parser;

final class MonologLogRecordScanner implements ScannerInterface
{
    private const MONOLOG_FORMATTER_PREFIXES = [
        'Monolog\\Formatter\\',
    ];

    private const MONOLOG_HANDLER_PREFIXES = [
        'Monolog\\Handler\\',
    ];

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
            $parentClass = $this->getParentClassName($class);
            if ($parentClass === null) {
                continue;
            }

            if (!$this->isMonologSubclass($parentClass)) {
                continue;
            }

            $className = $this->resolveClassName($class);
            $findings = array_merge($findings, $this->scanMethods($class, $filePath, $className, $parentClass));
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

    private function scanMethods(Class_ $class, string $filePath, string $className, string $parentClass): array
    {
        $findings = [];
        foreach ($class->getMethods() as $method) {
            $methodName = $method->name->toString();
            $recordParam = $this->findArrayRecordParam($method);
            if ($recordParam === null) {
                continue;
            }

            $paramName = $recordParam;
            $arrayKeys = $this->findArrayAccessKeys($method, $paramName);

            $line = $method->getStartLine();

            $findings[] = new Finding(
                ruleId: 'monolog-logrecord',
                severity: 'error',
                file: $filePath,
                line: $line,
                description: sprintf(
                    '%s() method uses array $record signature — Monolog 3.x requires LogRecord object',
                    $methodName
                ),
                context: [
                    'method' => $methodName,
                    'current_signature' => sprintf('%s(array $record)', $methodName),
                    'parent_class' => $parentClass,
                    'array_access_keys' => $arrayKeys,
                ],
                className: $className,
                phpVersion: '8.x',
            );
        }

        return $findings;
    }

    private function findArrayRecordParam(ClassMethod $method): ?string
    {
        foreach ($method->getParams() as $param) {
            if (!$param->var instanceof Variable) {
                continue;
            }

            $name = $param->var->name;
            if (is_string($name)) {
                $nameStr = $name;
            } elseif ($name instanceof Identifier) {
                $nameStr = $name->toString();
            } else {
                continue;
            }

            if ($nameStr !== 'record') {
                continue;
            }

            if ($this->isTypedAsArray($param)) {
                return 'record';
            }
        }

        return null;
    }

    private function isTypedAsArray(Param $param): bool
    {
        if ($param->type === null) {
            return false;
        }

        if ($param->type instanceof Identifier) {
            return $param->type->toLowerString() === 'array';
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function findArrayAccessKeys(ClassMethod $method, string $paramName): array
    {
        $keys = [];
        $collector = new class($paramName) extends NodeVisitorAbstract {
            private string $paramName;
            /** @var string[] */
            private array $collected = [];

            public function __construct(string $paramName)
            {
                $this->paramName = $paramName;
            }

            public function enterNode(Node $node): ?Node
            {
                if (!$node instanceof ArrayDimFetch) {
                    return null;
                }

                if (!$node->var instanceof Variable) {
                    return null;
                }

                $varName = $node->var->name;
                if ($varName instanceof Identifier) {
                    $varName = $varName->toString();
                } elseif (is_string($varName)) {
                    // already string
                } else {
                    return null;
                }

                if ($varName !== $this->paramName) {
                    return null;
                }

                if ($node->dim === null) {
                    return null;
                }

                if ($node->dim instanceof Node\Scalar\String_) {
                    $this->collected[] = $node->dim->value;
                }

                return null;
            }

            /**
             * @return string[]
             */
            public function getCollected(): array
            {
                return array_values(array_unique($this->collected));
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($collector);
        $traverser->traverse($method->stmts ?? []);

        return $collector->getCollected();
    }

    private function getParentClassName(Class_ $class): ?string
    {
        if ($class->extends === null) {
            return null;
        }

        return $this->resolveName($class->extends);
    }

    private function resolveName(Name $name): string
    {
        return $name->toString();
    }

    private function isMonologSubclass(string $parentClass): bool
    {
        $prefixes = array_merge(self::MONOLOG_FORMATTER_PREFIXES, self::MONOLOG_HANDLER_PREFIXES);
        foreach ($prefixes as $prefix) {
            if (str_starts_with($parentClass, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function resolveClassName(Class_ $class): string
    {
        if ($class->name instanceof Identifier) {
            return $class->name->toString();
        }
        return 'anonymous';
    }
}
