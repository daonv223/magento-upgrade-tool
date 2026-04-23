<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ZendDbExprToMagentoExpressionRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace new Zend_Db_Expr() with new Magento\Framework\DB\Sql\Expression()',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
new \Zend_Db_Expr('COUNT(*)');
new Zend_Db_Expr('IFNULL(foo, 0)');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
new \Magento\Framework\DB\Sql\Expression('COUNT(*)');
new \Magento\Framework\DB\Sql\Expression('IFNULL(foo, 0)');
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

        if (!$this->isName($className, 'Zend_Db_Expr')) {
            return null;
        }

        $node->class = new FullyQualified('Magento\Framework\DB\Sql\Expression');

        return $node;
    }
}