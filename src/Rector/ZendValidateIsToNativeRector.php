<?php

declare(strict_types=1);

namespace Betanet\Autofixer\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ZendValidateIsToNativeRector extends AbstractRector
{
    private const VALIDATOR_MAP = [
        'EmailAddress' => 'filter_var',
        'Zend_Validate_EmailAddress' => 'filter_var',
        'Int' => 'filter_var',
        'Zend_Validate_Int' => 'filter_var',
        'NotEmpty' => 'not_null_not_empty_string',
        'Zend_Validate_NotEmpty' => 'not_null_not_empty_string',
        'Regex' => 'preg_match',
        'Zend_Validate_Regex' => 'preg_match',
        'Alpha' => 'preg_match',
        'Zend_Validate_Alpha' => 'preg_match',
        'Alnum' => 'preg_match',
        'Zend_Validate_Alnum' => 'preg_match',
        'Digits' => 'ctype_digit',
        'Zend_Validate_Digits' => 'ctype_digit',
    ];

    private const FILTER_MAP = [
        'EmailAddress' => 'FILTER_VALIDATE_EMAIL',
        'Zend_Validate_EmailAddress' => 'FILTER_VALIDATE_EMAIL',
        'Int' => 'FILTER_VALIDATE_INT',
        'Zend_Validate_Int' => 'FILTER_VALIDATE_INT',
    ];

    private const PREG_MAP = [
        'Alpha' => '/^[a-zA-Z]+$/',
        'Zend_Validate_Alpha' => '/^[a-zA-Z]+$/',
        'Alnum' => '/^[a-zA-Z0-9]+$/',
        'Zend_Validate_Alnum' => '/^[a-zA-Z0-9]+$/',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Zend_Validate::is() with native PHP functions',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Zend_Validate::is($email, 'EmailAddress');
Zend_Validate::is($value, 'NotEmpty');
Zend_Validate::is($value, 'Regex', ['pattern' => '/^test$/']);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
filter_var($email, FILTER_VALIDATE_EMAIL);
$value !== null && $value !== '';
preg_match('/^test$/', $value);
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

        if (!$this->isName($node->name, 'is')) {
            return null;
        }

        if (!($node->class instanceof Name)) {
            return null;
        }

        if (!$this->isName($node->class, 'Zend_Validate')) {
            return null;
        }

        if (!isset($node->args[0], $node->args[1])) {
            return null;
        }

        $validatorArg = $node->args[1]->value;
        if (!$validatorArg instanceof String_) {
            return null;
        }

        $validatorName = $validatorArg->value;
        if (!isset(self::VALIDATOR_MAP[$validatorName])) {
            return null;
        }

        $valueArg = $node->args[0];

        return $this->createNativeCall($validatorName, $valueArg, $node);
    }

    private function createNativeCall(string $validator, Arg $valueArg, StaticCall $node): ?Node
    {
        $nativeFunc = self::VALIDATOR_MAP[$validator];

        if ($nativeFunc === 'filter_var') {
            $filterConst = self::FILTER_MAP[$validator];
            return new FuncCall(new Name('filter_var'), [
                $valueArg,
                new Arg(new ConstFetch(new Name($filterConst))),
            ]);
        }

        if ($nativeFunc === 'not_null_not_empty_string') {
            return new Node\Expr\BinaryOp\LogicalAnd(
                new NotIdentical($valueArg->value, new ConstFetch(new Name('null'))),
                new NotIdentical($valueArg->value, new String_(''))
            );
        }

        if ($nativeFunc === 'preg_match') {
            $pattern = $this->extractPattern($validator, $node);
            if ($pattern === null) {
                return null;
            }
            return new FuncCall(new Name('preg_match'), [
                new Arg(new String_($pattern)),
                $valueArg,
            ]);
        }

        if ($nativeFunc === 'ctype_digit') {
            return new FuncCall(new Name('ctype_digit'), [$valueArg]);
        }

        return null;
    }

    private function extractPattern(string $validator, StaticCall $node): ?string
    {
        if (isset($node->args[2])) {
            $optionsArg = $node->args[2]->value;
            if ($optionsArg instanceof Node\Expr\Array_) {
                foreach ($optionsArg->items as $item) {
                    if ($item->key instanceof String_ && $item->key->value === 'pattern' && $item->value instanceof String_) {
                        return $item->value->value;
                    }
                }
            }
        }

        return self::PREG_MAP[$validator] ?? null;
    }
}