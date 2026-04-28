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

Default target is the current PHP version. Override with `--php-version=`:

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
```

The scripts validate that the target path is a Magento project (checks for `bin/magento`) before running.

## Problem Scanner

Runs PHPStan against the Magento project to detect problems that need human/AI review — too context-dependent for blind Rector transforms. Outputs a JSON report (PHPStan native format, grouped by Magento module) for a subagent to later apply context-aware fixes.

Uses the target project's own PHPStan installation, so it picks up the project's autoloader, extensions, and configuration automatically.

Magento generated classes (Factory, Proxy, Interceptor, ExtensionInterface, ExtensionAttributesFactory) are filtered out automatically since they are not real errors.

### Scan for problems

```bash
bin/scan-problem /path/to/magento-project
```

### Custom paths and options

```bash
# Scan a specific vendor module
bin/scan-problem /path/to/magento --paths=/app/code/Betanet

# Custom PHPStan level (default: 0)
bin/scan-problem /path/to/magento --level=5

# Custom output path
bin/scan-problem /path/to/magento --output=/tmp/report.json

# Combine options
bin/scan-problem /path/to/magento --paths=/app/code/Betanet --level=3 --php-version=8.4
```

Default output: `<project>/reports/risky-findings-<paths>.json`

### JSON output format

PHPStan native JSON format, grouped by Magento module:

```json
{
  "totals": { "errors": 0, "file_errors": 85 },
  "files": {
    "Betanet_Bnewsletter": {
      "errors": 12,
      "files": {
        "/path/to/app/code/Betanet/Bnewsletter/Controller/Delete.php": {
          "errors": 1,
          "messages": [
            {
              "message": "Method ... should return ...",
              "line": 21,
              "ignorable": true,
              "identifier": "return.missing"
            }
          ]
        }
      }
    }
  },
  "errors": []
}
```

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
- **PHP version**: configurable via `--php-version=` flag or `PHP_VERSION` env var (default: current PHP version)
- **`MAGENTO_PATH`**: set automatically by the script, or pass via env var

To add rules, edit the `$rectorConfig->rules([...])` array in `rector.php`.

## Project structure

```
autofixer/
├── bin/fix-deprecation              Auto-safe fixer runner
├── bin/scan-problem                 Problem scanner (PHPStan-based)
├── bin/scan-risky.php               (legacy, unused)
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
├── src/Scanner/                     (legacy, unused)
├── composer.json                    Dependencies
├── tests/
│   ├── run-test.sh                  Auto-safe test runner (54 assertions)
│   └── fixture/                     Fake Magento project for testing
└── TARGET.MD                        Rule catalog (all 28 classified patterns)
```
