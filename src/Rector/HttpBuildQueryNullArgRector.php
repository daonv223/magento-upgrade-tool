<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class HttpBuildQueryNullArgRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace null 2nd arg in http_build_query with empty string',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
http_build_query($data, null);
http_build_query($data, null, '&');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
http_build_query($data, '');
http_build_query($data, '', '&');
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall) {
            return null;
        }

        if (!$this->isName($node->name, 'http_build_query')) {
            return null;
        }

        if (!isset($node->args[1])) {
            return null;
        }

        $secondArg = $node->args[1]->value;
        if ($secondArg instanceof Node\Expr\ConstFetch && $this->isName($secondArg->name, 'null')) {
            $node->args[1]->value = new String_('');
            return $node;
        }

        return null;
    }
}