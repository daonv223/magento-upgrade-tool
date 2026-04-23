<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class DateTimeNullToNowRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace new DateTime(null) with new DateTime("now")',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$dt = new \DateTime(null);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$dt = new \DateTime('now');
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof New_) {
            return null;
        }

        $className = $node->class;
        if (!($className instanceof Name || $className instanceof FullyQualified)) {
            return null;
        }

        if (!$this->isName($className, 'DateTime') && !$this->isName($className, 'DateTimeImmutable')) {
            return null;
        }

        if (!isset($node->args[0])) {
            return null;
        }

        $firstArg = $node->args[0]->value;

        if ($firstArg instanceof Node\Expr\ConstFetch && $this->isName($firstArg->name, 'null')) {
            $node->args[0]->value = new String_('now');
            return $node;
        }

        return null;
    }
}