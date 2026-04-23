<?php

declare(strict_types=1);

use Betanet\Autofixer\Rector\DateTimeNullToNowRector;
use Betanet\Autofixer\Rector\ForeachOnNullableRector;
use Betanet\Autofixer\Rector\HttpBuildQueryNullArgRector;
use Betanet\Autofixer\Rector\ZendDbExprToMagentoExpressionRector;
use Betanet\Autofixer\Rector\ZendHttpClientToLaminasRequestRector;
use Betanet\Autofixer\Rector\ZendJsonToNativeRector;
use Betanet\Autofixer\Rector\ZendDateIsDateToDateTimeRector;
use Betanet\Autofixer\Rector\ZendValidateIsToNativeRector;
use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\Config\RectorConfig;
use Rector\Php52\Rector\Switch_\ContinueToBreakInSwitchRector;
use Rector\Php72\Rector\Assign\ListEachRector;
use Rector\Php72\Rector\FuncCall\CreateFunctionToAnonymousFunctionRector;
use Rector\Php72\Rector\While_\WhileEachToForeachRector;
use Rector\Php74\Rector\ArrayDimFetch\CurlyToSquareBracketArrayStringRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php82\Rector\Encapsed\VariableInStringInterpolationFixerRector;
use Rector\Php82\Rector\FuncCall\Utf8DecodeEncodeToMbConvertEncodingRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Renaming\Rector\Name\RenameClassRector;

$phpVersionMap = [
    '8.0' => 80000,
    '8.1' => 80100,
    '8.2' => 80200,
    '8.3' => 80300,
    '8.4' => 80400,
    '8.5' => 80500,
];

return static function (RectorConfig $rectorConfig) use ($phpVersionMap): void {
    $projectPath = getenv('MAGENTO_PATH') ?: getcwd();

    $scanPaths = getenv('SCAN_PATHS') ?: '/app/code,/app/design';
    $paths = array_map(
        static fn(string $p): string => $projectPath . $p,
        array_filter(explode(',', $scanPaths)),
    );
    $paths = array_filter($paths, 'is_dir');

    if (empty($paths)) {
        throw new \RuntimeException(
            'No valid scan paths found. Set MAGENTO_PATH and SCAN_PATHS env vars.'
            . ' SCAN_PATHS defaults to "/app/code,/app/design"'
        );
    }

    $rectorConfig->paths(array_values($paths));

    $phpVersionRaw = getenv('PHP_VERSION') ?: '8.3';
    $phpVersion = $phpVersionMap[$phpVersionRaw] ?? (is_numeric($phpVersionRaw) ? (int) $phpVersionRaw : 80300);
    $rectorConfig->phpVersion($phpVersion);

    $rectorConfig->importNames(false);
    $rectorConfig->importShortClasses(false);

    $rectorConfig->rules([
        ContinueToBreakInSwitchRector::class,
        CurlyToSquareBracketArrayStringRector::class,
        NullToStrictStringFuncCallArgRector::class,
        Utf8DecodeEncodeToMbConvertEncodingRector::class,
        VariableInStringInterpolationFixerRector::class,
        ExplicitNullableParamTypeRector::class,
        WhileEachToForeachRector::class,
        ListEachRector::class,
        CreateFunctionToAnonymousFunctionRector::class,
        ZendJsonToNativeRector::class,
        ZendValidateIsToNativeRector::class,
        ZendDbExprToMagentoExpressionRector::class,
        ZendHttpClientToLaminasRequestRector::class,
        ForeachOnNullableRector::class,
        DateTimeNullToNowRector::class,
        HttpBuildQueryNullArgRector::class,
        ZendDateIsDateToDateTimeRector::class,
        CompleteDynamicPropertiesRector::class,
    ]);

    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, [
        'Zend_Currency' => 'Magento\Framework\Currency',
        'Zend_Filter_LocalizedToNormalized' => 'Magento\Framework\Filter\LocalizedToNormalized',
        'Zend_Filter_Interface' => 'Laminas\Filter\FilterInterface',
        'Zend_Uri' => 'Laminas\Uri\Uri',
        'Zend_Soap_Client' => 'Laminas\Soap\Client',
        'Zend_Filter_Input' => 'Magento\Framework\Filter\FilterInput',
        'Zend\Uri\UriFactory' => 'Laminas\Uri\UriFactory',
        'Zend\Uri\Uri' => 'Laminas\Uri\Uri',
        'Zend_Json_Exception' => 'JsonException',
        'Zend_Currency_Exception' => 'Exception',
        'Zend_Validate_Exception' => 'Exception',
    ]);
};