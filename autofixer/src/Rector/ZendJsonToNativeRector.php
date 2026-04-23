<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ZendJsonToNativeRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Zend_Json::encode() with json_encode() and Zend_Json::decode() with json_decode()',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Zend_Json::encode($data);
Zend_Json::decode($json);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
json_encode($data);
json_decode($json, true);
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

        if (!$this->isName($node->name, 'encode') && !$this->isName($node->name, 'decode')) {
            return null;
        }

        if (!($node->class instanceof Name)) {
            return null;
        }

        if (!$this->isName($node->class, 'Zend_Json')) {
            return null;
        }

        if ($this->isName($node->name, 'encode')) {
            return new FuncCall(new Name('json_encode'), $node->args);
        }

        $args = $node->args;
        if (!isset($args[1])) {
            $args[1] = new Arg(
                new Node\Expr\ConstFetch(new Name('true'))
            );
        }

        return new FuncCall(new Name('json_decode'), $args);
    }
}