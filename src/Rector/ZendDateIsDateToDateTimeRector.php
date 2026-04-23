<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ZendDateIsDateToDateTimeRector extends AbstractRector
{
    private const ZEND_FORMAT_MAP = [
        'yyyy-MM-dd' => 'Y-m-d',
        'yyyy/MM/dd' => 'Y/m/d',
        'dd-MM-yyyy' => 'd-m-Y',
        'dd/MM/yyyy' => 'd/m/Y',
        'MM/dd/yyyy' => 'm/d/Y',
        'yyyy-MM-dd HH:mm:ss' => 'Y-m-d H:i:s',
        'HH:mm:ss' => 'H:i:s',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Zend_Date::isDate() with DateTime::createFromFormat()',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Zend_Date::isDate($dateStr);
Zend_Date::isDate($dateStr, 'yyyy-MM-dd');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
DateTime::createFromFormat('Y-m-d', (string) $dateStr) !== false;
DateTime::createFromFormat('Y-m-d', (string) $dateStr) !== false;
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof StaticCall) {
            return null;
        }

        if (!$this->isName($node->name, 'isDate')) {
            return null;
        }

        $className = $node->class;
        if (!($className instanceof Name)) {
            return null;
        }

        if (!$this->isName($className, 'Zend_Date')) {
            return null;
        }

        if (!isset($node->args[0])) {
            return null;
        }

        $dateArg = $node->args[0];
        $format = 'Y-m-d';

        if (isset($node->args[1])) {
            $formatArg = $node->args[1]->value;
            if ($formatArg instanceof String_) {
                if (!isset(self::ZEND_FORMAT_MAP[$formatArg->value])) {
                    return null;
                }
                $format = self::ZEND_FORMAT_MAP[$formatArg->value];
            }
        }

        $createFromDate = new StaticCall(
            new FullyQualified('DateTime'),
            'createFromFormat',
            [
                new Arg(new String_($format)),
                new Arg(new Node\Expr\Cast\String_($dateArg->value)),
            ]
        );

        return new Node\Expr\BinaryOp\NotIdentical(
            $createFromDate,
            new Node\Expr\ConstFetch(new Name('false'))
        );
    }
}