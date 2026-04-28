<?php

declare(strict_types=1);

use Betanet\Autofixer\Scanner\Finding;

require_once __DIR__ . '/../vendor/autoload.php';

$projectPath = realpath(getenv('MAGENTO_PATH') ?: getcwd());
$phpVersion = getenv('PHP_VERSION') ?: '8.3';
$outputPath = getenv('SCAN_RISKY_OUTPUT') ?: $projectPath . '/reports/risky-findings.json';
$phpstanJsonFile = getenv('PHPSTAN_JSON_FILE') ?: '';

if ($phpstanJsonFile === '' || !is_file($phpstanJsonFile)) {
    fwrite(STDERR, "Error: PHPSTAN_JSON_FILE not set or file not found\n");
    exit(1);
}

$raw = file_get_contents($phpstanJsonFile);
$phpstanResult = json_decode($raw, true);

if (!is_array($phpstanResult) || !isset($phpstanResult['files'])) {
    fwrite(STDERR, "Error: invalid PHPStan JSON output\n");
    exit(1);
}

$severityMap = [
    'deprecated' => 'warning',
    'phpstanDeprecation' => 'warning',
];

$findings = [];

foreach ($phpstanResult['files'] as $absoluteFile => $fileData) {
    $relativePath = str_replace($projectPath . '/', '', $absoluteFile);
    $relativePath = str_replace($projectPath . DIRECTORY_SEPARATOR, '', $relativePath);

    foreach ($fileData['messages'] as $msg) {
        $identifier = $msg['identifier'] ?? 'unknown';
        $severity = 'error';
        foreach ($severityMap as $keyword => $level) {
            if (str_contains($identifier, $keyword)) {
                $severity = $level;
                break;
            }
        }

        $context = ['identifier' => $identifier];
        if (isset($msg['tip'])) {
            $context['tip'] = $msg['tip'];
        }

        $findings[] = new Finding(
            ruleId: $identifier,
            severity: $severity,
            file: $relativePath,
            line: $msg['line'] ?? 0,
            description: $msg['message'],
            context: $context,
        );
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
