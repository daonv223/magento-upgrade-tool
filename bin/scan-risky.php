<?php

declare(strict_types=1);

use Betanet\Autofixer\Scanner\ConstructorParamReorderScanner;
use Betanet\Autofixer\Scanner\Finding;
use Betanet\Autofixer\Scanner\MonologLogRecordScanner;
use Betanet\Autofixer\Scanner\ScannerInterface;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/rector/rector/vendor/autoload.php';

$projectPath = realpath(getenv('MAGENTO_PATH') ?: getcwd());
$scanPathsRaw = getenv('SCAN_PATHS') ?: '/app/code,/app/design';
$phpVersion = getenv('PHP_VERSION') ?: '8.3';
$outputPath = getenv('SCAN_RISKY_OUTPUT') ?: $projectPath . '/reports/risky-findings.json';

for ($i = 1; $i < $argc; $i++) {
    if (str_starts_with($argv[$i], '--paths=')) {
        $scanPathsRaw = substr($argv[$i], 8);
    } elseif (str_starts_with($argv[$i], '--php-version=')) {
        $phpVersion = substr($argv[$i], 14);
    } elseif (str_starts_with($argv[$i], '--output=')) {
        $outputPath = substr($argv[$i], 9);
    }
}

$scanPaths = array_filter(
    array_map(
        static fn(string $p): string => $projectPath . $p,
        array_filter(explode(',', $scanPathsRaw)),
    ),
    'is_dir',
);

if (empty($scanPaths)) {
    fwrite(STDERR, "No valid scan paths found. Set MAGENTO_PATH and SCAN_PATHS env vars.\n");
    exit(1);
}

$scanners = [
    new MonologLogRecordScanner(),
    new ConstructorParamReorderScanner(),
];

$findings = [];
foreach ($scanPaths as $scanPath) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanPath, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $filePath = $fileInfo->getRealPath();

        foreach ($scanners as $scanner) {
            $results = $scanner->scan($filePath);
            foreach ($results as $finding) {
                $relativePath = str_replace($projectPath . '/', '', $filePath);
                $relativePath = str_replace($projectPath . DIRECTORY_SEPARATOR, '', $relativePath);
                $findings[] = new Finding(
                    ruleId: $finding->ruleId,
                    severity: $finding->severity,
                    file: $relativePath,
                    line: $finding->line,
                    description: $finding->description,
                    context: $finding->context,
                    className: $finding->className,
                    phpVersion: $finding->phpVersion,
                );
            }
        }
    }
}

usort($findings, static fn(Finding $a, Finding $b): int => strcmp($a->file, $b->file) ?: $a->line <=> $b->line);

$summary = [];
foreach ($findings as $finding) {
    if (!isset($summary[$finding->ruleId])) {
        $summary[$finding->ruleId] = 0;
    }
    $summary[$finding->ruleId]++;
}

$report = [
    'scan_date' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
    'target' => $projectPath,
    'php_version' => $phpVersion,
    'total_findings' => count($findings),
    'findings' => array_map(static fn(Finding $f): array => $f->toArray(), $findings),
    'summary' => $summary,
];

$outputDir = dirname($outputPath);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo "Scan complete: " . count($findings) . " finding(s) written to {$outputPath}\n";
exit(0);
