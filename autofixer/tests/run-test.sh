#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FIXTURE_SRC="$(cd "$(dirname "$0")/fixture" && pwd)"
PASS=0
FAIL=0

assert_contains() {
    local label="$1" haystack="$2" pattern="$3"
    if echo "$haystack" | rg -q "$pattern" 2>/dev/null; then
        echo "  ✓ $label"
        PASS=$((PASS + 1))
    else
        echo "  ✗ $label — expected '$pattern'"
        FAIL=$((FAIL + 1))
    fi
}

assert_not_contains() {
    local label="$1" haystack="$2" pattern="$3"
    local added_only
    added_only=$(echo "$haystack" | rg '^\+' || true)
    if ! echo "$added_only" | rg -q "$pattern" 2>/dev/null; then
        echo "  ✓ $label"
        PASS=$((PASS + 1))
    else
        echo "  ✗ $label — did not expect '$pattern'"
        FAIL=$((FAIL + 1))
    fi
}

run_dry() {
    local fixture_dir
    fixture_dir=$(mktemp -d)
    cp -r "$FIXTURE_SRC"/* "$fixture_dir/"
    local output
    output=$("$SCRIPT_DIR/bin/fix-deprecation" "$fixture_dir" "$@" --dry-run 2>&1)
    rm -rf "$fixture_dir"
    echo "$output"
}

OUTPUT=$(run_dry)

echo "=== Test 1: Default paths (--dry-run) ==="
EXIT1=0
run_dry > /dev/null || EXIT1=$?
if [ "$EXIT1" -eq 0 ] || [ "$EXIT1" -eq 1 ] || [ "$EXIT1" -eq 2 ]; then
    echo "  ✓ dry-run with default paths succeeded"
    PASS=$((PASS + 1))
else
    echo "  ✗ dry-run with default paths failed (exit $EXIT1)"
    FAIL=$((FAIL + 1))
fi

echo ""
echo "=== Test 2: Custom --paths flag ==="
EXIT2=0
run_dry --paths=/app/code > /dev/null || EXIT2=$?
if [ "$EXIT2" -eq 0 ] || [ "$EXIT2" -eq 1 ] || [ "$EXIT2" -eq 2 ]; then
    echo "  ✓ dry-run with --paths=/app/code succeeded"
    PASS=$((PASS + 1))
else
    echo "  ✗ dry-run with --paths=/app/code failed (exit $EXIT2)"
    FAIL=$((FAIL + 1))
fi

echo ""
echo "=== Test 3: Custom --php-version flag ==="
EXIT3=0
run_dry --php-version=8.2 > /dev/null || EXIT3=$?
if [ "$EXIT3" -eq 0 ] || [ "$EXIT3" -eq 1 ] || [ "$EXIT3" -eq 2 ]; then
    echo "  ✓ dry-run with --php-version=8.2 succeeded"
    PASS=$((PASS + 1))
else
    echo "  ✗ dry-run with --php-version=8.2 failed (exit $EXIT3)"
    FAIL=$((FAIL + 1))
fi

echo ""
echo "=== Verifying dry-run diff output ==="

echo ""
echo "--- NullToStrictString ---"
assert_contains "trim casts to string" "$OUTPUT" 'trim\(\(string\)'
assert_contains "strtoupper casts to string" "$OUTPUT" 'strtoupper\(\(string\)'
assert_not_contains "no bare trim(\$var) in diff" "$OUTPUT" 'trim\(\$[a-z]+\)'

echo ""
echo "--- Utf8DecodeEncodeToMbConvertEncoding ---"
assert_contains "mb_convert_encoding in diff" "$OUTPUT" 'mb_convert_encoding'
assert_not_contains "no utf8_encode in diff" "$OUTPUT" 'utf8_encode'
assert_not_contains "no utf8_decode in diff" "$OUTPUT" 'utf8_decode'

echo ""
echo "--- ZendJsonToNative ---"
assert_contains "json_encode in diff" "$OUTPUT" 'json_encode'
assert_contains "json_decode in diff" "$OUTPUT" 'json_decode'
assert_not_contains "no Zend_Json in diff" "$OUTPUT" 'Zend_Json::'

echo ""
echo "--- ZendValidateIsToNative ---"
assert_contains "filter_var for EmailAddress" "$OUTPUT" 'filter_var.*FILTER_VALIDATE_EMAIL'
assert_contains "filter_var for Int" "$OUTPUT" 'filter_var.*FILTER_VALIDATE_INT'
assert_contains "!== null && !== '' for NotEmpty" "$OUTPUT" '!== null.*!=='
assert_contains "preg_match for Regex" "$OUTPUT" 'preg_match'
assert_contains "ctype_digit for Digits" "$OUTPUT" 'ctype_digit'
assert_not_contains "no Zend_Validate::is in diff" "$OUTPUT" 'Zend_Validate::is'
assert_not_contains "no /.*/ fallback in diff" "$OUTPUT" '/\.\*/'

echo ""
echo "--- ZendDbExprToMagentoExpression ---"
assert_contains "Magento Expression in diff" "$OUTPUT" 'Magento.*Framework.*DB.*Sql.*Expression'
assert_not_contains "no Zend_Db_Expr in diff" "$OUTPUT" 'Zend_Db_Expr'

echo ""
echo "--- ZendHttpClientToLaminasRequest ---"
assert_contains "Laminas METHOD_GET in diff" "$OUTPUT" 'Laminas.*Http.*Request::METHOD_GET'
assert_contains "Laminas METHOD_POST in diff" "$OUTPUT" 'Laminas.*Http.*Request::METHOD_POST'
assert_not_contains "no Zend_Http_Client in diff" "$OUTPUT" 'Zend_Http_Client'

echo ""
echo "--- ForeachOnNullable ---"
assert_contains "foreach ?? [] in diff" "$OUTPUT" 'foreach.*\?\? \[\]'

echo ""
echo "--- DateTimeNullToNow ---"
assert_contains "DateTime('now') in diff" "$OUTPUT" "'now'"

echo ""
echo "--- HttpBuildQueryNullArg ---"
assert_contains "http_build_query with '' in diff" "$OUTPUT" "http_build_query.*''"

echo ""
echo "--- ZendDateIsDateToDateTime ---"
assert_contains "DateTime::createFromFormat in diff" "$OUTPUT" 'DateTime::createFromFormat'
assert_not_contains "no Zend_Date::isDate in diff" "$OUTPUT" 'Zend_Date::isDate'
assert_not_contains "unmapped format not silently converted" "$OUTPUT" "createFromFormat.*EEE"

echo ""
echo "--- Zend class renames (RenameClassRector) ---"
assert_contains "Magento Currency in diff" "$OUTPUT" 'Magento.*Framework.*Currency'
assert_contains "Laminas Filter FilterInterface in diff" "$OUTPUT" 'Laminas.*Filter.*FilterInterface'
assert_contains "Laminas Uri in diff" "$OUTPUT" 'Laminas.*Uri'
assert_contains "Laminas Soap Client in diff" "$OUTPUT" 'Laminas.*Soap.*Client'
assert_contains "Magento FilterInput in diff" "$OUTPUT" 'Magento.*Framework.*Filter.*FilterInput'
assert_contains "Laminas UriFactory in diff" "$OUTPUT" 'Laminas.*Uri.*UriFactory'
assert_contains "JsonException in diff" "$OUTPUT" 'JsonException'
assert_not_contains "no Zend_Currency in diff" "$OUTPUT" 'Zend_Currency'
assert_not_contains "no Zend_Filter_Interface in diff" "$OUTPUT" 'Zend_Filter_Interface'
assert_not_contains "no Zend_Uri in diff" "$OUTPUT" 'Zend_Uri'
assert_not_contains "no Zend_Soap_Client in diff" "$OUTPUT" 'Zend_Soap_Client'
assert_not_contains "no Zend_Uri_UriFactory in diff" "$OUTPUT" 'Zend\\?Uri\\?UriFactory'
assert_not_contains "no Zend_Json_Exception in diff" "$OUTPUT" 'Zend_Json_Exception'
assert_not_contains "no Zend_Currency_Exception in diff" "$OUTPUT" 'Zend_Currency_Exception'
assert_not_contains "no Zend_Validate_Exception in diff" "$OUTPUT" 'Zend_Validate_Exception'

echo ""
echo "--- ExplicitNullableParamType (requires PHP 8.4) ---"
OUTPUT_84=$(run_dry --php-version=8.4)
assert_contains "?string nullable param in diff" "$OUTPUT_84" '\?string.*\$name.*=.*null'
assert_contains "?int nullable param in diff" "$OUTPUT_84" '\?int.*\$id.*=.*null'
assert_contains "?array nullable param in diff" "$OUTPUT_84" '\?array.*\$options.*=.*null'

echo ""
echo "--- Each / CreateFunction removal ---"
assert_contains "foreach replaces while-each in diff" "$OUTPUT" 'foreach.*\$data'
assert_not_contains "no each() in diff" "$OUTPUT" '\beach\('
assert_not_contains "no create_function in diff" "$OUTPUT" 'create_function'

echo ""
echo "--- CompleteDynamicProperties ---"
assert_contains "declared property for customField in diff" "$OUTPUT" 'private string \$customField'
assert_contains "declared property for extraData in diff" "$OUTPUT" 'private array \$extraData'
assert_contains "declared property for runtimeProp in diff" "$OUTPUT" 'public \$runtimeProp'

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi