# Magento Upgrade Auto-Fixer

Automated PHP 8.x deprecation fixer for Magento projects. Uses Rector to apply deterministic fixes that would otherwise consume hours of manual work during upgrades.

## Requirements

- PHP 8.3+
- Composer

## Install

```bash
cd autofixer
composer install
```

## Usage

### Dry run (preview changes)

```bash
bin/fix-deprecation /path/to/magento-project --dry-run
```

### Apply fixes

```bash
bin/fix-deprecation /path/to/magento-project
```

### Custom scan paths

By default the tool scans `/app/code` and `/app/design` relative to the project root. Override with `--paths=`:

```bash
# Scan only a specific module
bin/fix-deprecation /path/to/magento --paths=/app/code/Betanet

# Scan multiple paths
bin/fix-deprecation /path/to/magento --paths=/app/code,/vendor/some-module

# Or via env var
SCAN_PATHS=/app/code,/vendor/MyModule bin/fix-deprecation /path/to/magento
```

Non-existent directories are skipped automatically.

### Custom PHP version

Default target is PHP 8.3. Override with `--php-version=`:

```bash
bin/fix-deprecation /path/to/magento --php-version=8.2

# Or via env var
PHP_VERSION=8.2 bin/fix-deprecation /path/to/magento
```

Supported values: `8.0`, `8.1`, `8.2`, `8.3`, `8.4`, `8.5`, or raw integers like `80200`.

### Run tests

```bash
# Auto-safe fixer tests
tests/run-test.sh

# Risky pattern scanner tests
tests/run-test-scan-risky.sh
```

The scripts validate that the target path is a Magento project (checks for `bin/magento`) before running.

## Risky Pattern Scanner

Detects patterns that need human/AI review before fixing — too context-dependent for blind Rector transforms. Outputs a JSON report for a subagent to later apply context-aware fixes.

### Scan for risky patterns

```bash
bin/scan-risky /path/to/magento-project
```

### Custom output path

```bash
bin/scan-risky /path/to/magento-project --output=/tmp/report.json
```

Default output: `<project>/reports/risky-findings.json`

### Scanner options

Same flags as `bin/fix-deprecation`:

```bash
bin/scan-risky /path/to/magento --paths=/app/code --php-version=8.4
```

### Detection rules

| Rule ID | Severity | Detects |
|---|---|---|
| `monolog-logrecord` | error | Classes extending `Monolog\Formatter\*` or `Monolog\Handler\*` with `format(array $record)` — Monolog 3.x requires `LogRecord` object |
| `constructor-param-reorder` | warning | Constructors where required typed params appear after optional params — fatal in PHP 8.x |

### JSON output format

```json
{
  "scan_date": "2026-04-23T12:00:00Z",
  "target": "/path/to/magento",
  "php_version": "8.3",
  "total_findings": 2,
  "findings": [
    {
      "rule_id": "monolog-logrecord",
      "severity": "error",
      "file": "app/code/Amasty/Base/Debug/System/AmastyFormatter.php",
      "line": 18,
      "description": "format() method uses array $record signature — Monolog 3.x requires LogRecord object",
      "context": {
        "method": "format",
        "current_signature": "format(array $record)",
        "parent_class": "Monolog\\Formatter\\LineFormatter",
        "array_access_keys": ["message", "level"]
      },
      "class": "AmastyFormatter",
      "php_version": "8.x"
    }
  ],
  "summary": {
    "monolog-logrecord": 1,
    "constructor-param-reorder": 1
  }
}
```

This scanner is **read-only** — no code modifications. Fixing via subagent is a separate future task.

## Active rules

### Custom Rector rules

| Rule | PHP Version | Fix |
|---|---|---|
| `ZendJsonToNativeRector` | 8.3 | `Zend_Json::encode($d)` → `json_encode($d)`, `Zend_Json::decode($j)` → `json_decode($j, true)` |
| `ZendValidateIsToNativeRector` | 8.3 | `Zend_Validate::is($v, 'EmailAddress')` → `filter_var($v, FILTER_VALIDATE_EMAIL)`, `'NotEmpty'` → `!empty()`, `'Regex'` → `preg_match()`, `'Digits'` → `ctype_digit()` |
| `ZendDbExprToMagentoExpressionRector` | 8.3 | `new Zend_Db_Expr($sql)` → `new \Magento\Framework\DB\Sql\Expression($sql)` |
| `ZendHttpClientToLaminasRequestRector` | 8.3 | `Zend_Http_Client::GET` → `\Laminas\Http\Request::METHOD_GET` |
| `ForeachOnNullableRector` | 8.0 | `foreach($arr as $x)` → `foreach($arr ?? [] as $x)` (when `$arr` is nullable param) |
| `DateTimeNullToNowRector` | 8.1 | `new DateTime(null)` → `new DateTime('now')` |
| `HttpBuildQueryNullArgRector` | 8.1 | `http_build_query($d, null)` → `http_build_query($d, '')` |
| `ZendDateIsDateToDateTimeRector` | 8.3 | `Zend_Date::isDate($d)` → `DateTime::createFromFormat('Y-m-d', (string)$d) !== false` |

### Built-in Rector rules

| Rule | PHP Version | Fix |
|---|---|---|
| `NullToStrictStringFuncCallArgRector` | 8.1 | `trim($x)` → `trim((string) $x)`, null-safe string function args |
| `Utf8DecodeEncodeToMbConvertEncodingRector` | 8.2 | `utf8_encode($s)` → `mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1')` |
| `RenameClassRector` | 8.3 | Class renames (see below) |

### Class renames (via RenameClassRector)

| Old | New |
|---|---|
| `Zend_Currency` | `Magento\Framework\Currency` |
| `Zend_Filter_LocalizedToNormalized` | `Magento\Framework\Filter\LocalizedToNormalized` |
| `Zend_Filter_Interface` | `Laminas\Filter\FilterInterface` |
| `Zend_Uri` | `Laminas\Uri\Uri` |
| `Zend_Soap_Client` | `Laminas\Soap\Client` |
| `Zend_Filter_Input` | `Magento\Framework\Filter\FilterInput` |
| `Zend\Uri\UriFactory` | `Laminas\Uri\UriFactory` |
| `Zend\Uri\Uri` | `Laminas\Uri\Uri` |
| `Zend_Json_Exception` | `JsonException` |
| `Zend_Currency_Exception` | `Exception` |
| `Zend_Validate_Exception` | `Exception` |

## Configuration

Rector config is in `rector.php`. Key settings:

- **Target paths**: configurable via `--paths=` flag or `SCAN_PATHS` env var (default: `/app/code,/app/design`)
- **PHP version**: configurable via `--php-version=` flag or `PHP_VERSION` env var (default: `8.3`)
- **`MAGENTO_PATH`**: set automatically by the script, or pass via env var

To add rules, edit the `$rectorConfig->rules([...])` array in `rector.php`.

## Project structure

```
autofixer/
├── bin/fix-deprecation              Auto-safe fixer runner
├── bin/scan-risky                   Risky pattern scanner
├── rector.php                       Rector configuration
├── src/Rector/                      Custom Rector rules (auto-safe)
│   ├── DateTimeNullToNowRector.php
│   ├── ForeachOnNullableRector.php
│   ├── HttpBuildQueryNullArgRector.php
│   ├── ZendDbExprToMagentoExpressionRector.php
│   ├── ZendHttpClientToLaminasRequestRector.php
│   ├── ZendJsonToNativeRector.php
│   ├── ZendValidateIsToNativeRector.php
│   └── ZendDateIsDateToDateTimeRector.php
├── src/Scanner/                     Risky pattern scanners
│   ├── Finding.php
│   ├── ScannerInterface.php
│   ├── MonologLogRecordScanner.php
│   └── ConstructorParamReorderScanner.php
├── composer.json                    Dependencies
├── phpcs.xml.dist                   PHPCompatibility config
├── tests/
│   ├── run-test.sh                  Auto-safe test runner (54 assertions)
│   ├── run-test-scan-risky.sh       Risky scanner test runner (28 assertions)
│   └── fixture/                     Fake Magento project for testing
└── TARGET.MD                        Rule catalog (all 28 classified patterns)
```