<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ZendHttpClientToLaminasRequestRector extends AbstractRector
{
    private const METHOD_MAP = [
        'GET' => 'METHOD_GET',
        'POST' => 'METHOD_POST',
        'PUT' => 'METHOD_PUT',
        'DELETE' => 'METHOD_DELETE',
        'HEAD' => 'METHOD_HEAD',
        'OPTIONS' => 'METHOD_OPTIONS',
        'TRACE' => 'METHOD_TRACE',
        'CONNECT' => 'METHOD_CONNECT',
        'PATCH' => 'METHOD_PATCH',
        'PROPFIND' => 'METHOD_PROPFIND',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Zend_Http_Client constant references with Laminas\Http\Request constants',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$method = \Zend_Http_Client::GET;
$method = Zend_Http_Client::POST;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$method = \Laminas\Http\Request::METHOD_GET;
$method = \Laminas\Http\Request::METHOD_POST;
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [ClassConstFetch::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof ClassConstFetch) {
            return null;
        }

        $className = $node->class;
        if (!($className instanceof Name || $className instanceof FullyQualified)) {
            return null;
        }

        if (!$this->isName($className, 'Zend_Http_Client')) {
            return null;
        }

        $constName = $this->getName($node->name);
        if ($constName === null || !isset(self::METHOD_MAP[$constName])) {
            return null;
        }

        return new ClassConstFetch(
            new FullyQualified('Laminas\Http\Request'),
            new Identifier(self::METHOD_MAP[$constName])
        );
    }
}