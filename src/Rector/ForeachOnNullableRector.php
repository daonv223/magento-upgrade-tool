<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\UnionType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ForeachOnNullableRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add null coalescing to foreach with nullable iterable: foreach($arr as $x) → foreach($arr ?? [] as $x)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
function process(?array $items) {
    foreach ($items as $item) {
        echo $item;
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
function process(?array $items) {
    foreach ($items ?? [] as $item) {
        echo $item;
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FunctionLike) {
            return null;
        }

        $nullableParams = $this->getNullableIterableParamNames($node);
        if ($nullableParams === []) {
            return null;
        }

        $changed = false;

        $this->traverseNodesWithCallable($node->getStmts() ?? [], function (Node $inner) use ($nullableParams, &$changed): ?Foreach_ {
            if (!$inner instanceof Foreach_) {
                return null;
            }

            if ($inner->expr instanceof Coalesce) {
                return null;
            }

            if (!$inner->expr instanceof Variable) {
                return null;
            }

            $varName = $this->getName($inner->expr);
            if ($varName === null || !isset($nullableParams[$varName])) {
                return null;
            }

            $inner->expr = new Coalesce($inner->expr, new Node\Expr\Array_());
            $changed = true;
            return $inner;
        });

        return $changed ? $node : null;
    }

    /**
     * @return array<string, true>
     */
    private function getNullableIterableParamNames(FunctionLike $node): array
    {
        $names = [];
        foreach ($node->getParams() as $param) {
            if (!$param->var instanceof Variable) {
                continue;
            }
            $name = $this->getName($param->var);
            if ($name !== null && $this->isNullableIterableType($param->type)) {
                $names[$name] = true;
            }
        }
        return $names;
    }

    private function isNullableIterableType(?Node $type): bool
    {
        if ($type === null) {
            return false;
        }

        if ($type instanceof NullableType) {
            return $this->isIterableIdentifier($type->type);
        }

        if ($type instanceof UnionType) {
            $hasNull = false;
            $hasIterable = false;
            foreach ($type->types as $inner) {
                if ($inner instanceof Node\Identifier && $inner->toLowerString() === 'null') {
                    $hasNull = true;
                }
                if ($this->isIterableIdentifier($inner)) {
                    $hasIterable = true;
                }
            }
            return $hasNull && $hasIterable;
        }

        return false;
    }

    private function isIterableIdentifier(Node $node): bool
    {
        if (!$node instanceof Node\Identifier) {
            return false;
        }
        return in_array($node->toLowerString(), ['array', 'iterable'], true);
    }
}