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
    if ! echo "$haystack" | rg -q "$pattern" 2>/dev/null; then
        echo "  ✓ $label"
        PASS=$((PASS + 1))
    else
        echo "  ✗ $label — did not expect '$pattern'"
        FAIL=$((FAIL + 1))
    fi
}

assert_eq() {
    local label="$1" actual="$2" expected="$3"
    if [ "$actual" = "$expected" ]; then
        echo "  ✓ $label"
        PASS=$((PASS + 1))
    else
        echo "  ✗ $label — expected '$expected', got '$actual'"
        FAIL=$((FAIL + 1))
    fi
}

# Prepare a temp copy of the fixture project
fixture_dir=$(mktemp -d)
cp -r "$FIXTURE_SRC"/* "$fixture_dir/"

OUTPUT_FILE=$(mktemp)

echo "=== Test 1: scan-risky produces valid JSON ==="
EXIT1=0
"$SCRIPT_DIR/bin/scan-risky" "$fixture_dir" "--output=$OUTPUT_FILE" 2>/dev/null || EXIT1=$?
if [ "$EXIT1" -eq 0 ]; then
    echo "  ✓ scan-risky exits 0"
    PASS=$((PASS + 1))
else
    echo "  ✗ scan-risky exited with code $EXIT1"
    FAIL=$((FAIL + 1))
fi

JSON=$(cat "$OUTPUT_FILE")

echo ""
echo "=== Test 2: JSON has required top-level fields ==="
assert_contains "has scan_date" "$JSON" '"scan_date"'
assert_contains "has target" "$JSON" '"target"'
assert_contains "has php_version" "$JSON" '"php_version"'
assert_contains "has total_findings" "$JSON" '"total_findings"'
assert_contains "has findings array" "$JSON" '"findings"'
assert_contains "has summary" "$JSON" '"summary"'

echo ""
echo "=== Test 3: monolog-logrecord finding detected ==="
assert_contains "monolog-logrecord rule_id" "$JSON" '"rule_id".*"monolog-logrecord"'
assert_contains "MonologFormatterExample in file" "$JSON" 'MonologFormatterExample'
assert_contains "severity is error" "$JSON" '"severity".*"error"'
assert_contains "format method detected" "$JSON" '"method".*"format"'
assert_contains "parent_class Monolog LineFormatter" "$JSON" '"parent_class".*"Monolog.*Formatter.*LineFormatter"'
assert_contains "array_access_keys has message" "$JSON" '"message"'
assert_contains "array_access_keys has level" "$JSON" '"level"'
assert_contains "array_access_keys has channel" "$JSON" '"channel"'

echo ""
echo "=== Test 4: constructor-param-reorder finding detected ==="
assert_contains "constructor-param-reorder rule_id" "$JSON" '"rule_id".*"constructor-param-reorder"'
assert_contains "OAuth2Client class detected" "$JSON" '"OAuth2Client"'
assert_contains "severity is warning" "$JSON" '"severity".*"warning"'
assert_contains "params in context" "$JSON" '"params"'
assert_contains "helperData in params" "$JSON" 'HelperData'

echo ""
echo "=== Test 5: no false positives on auto-safe fixtures ==="
assert_not_contains "no monolog finding in ZendJsonExample" "$JSON" 'ZendJsonExample'
assert_not_contains "no monolog finding in NullToStringExample" "$JSON" 'NullToStringExample'
assert_not_contains "no constructor reorder in DynamicPropertyExample" "$JSON" 'DynamicPropertyExample.*constructor-param-reorder'

echo ""
echo "=== Test 6: ValidConstructorExample has no reorder finding ==="
# ValidConstructorExample should NOT trigger — typed param before optional
assert_not_contains "no reorder in ValidConstructorExample" "$JSON" 'ValidConstructorExample.*constructor-param-reorder'

echo ""
echo "=== Test 7: total_findings count matches actual count ==="
TOTAL_FINDINGS=$(echo "$JSON" | rg -o '"rule_id"' | wc -l | tr -d ' ')
TOTAL_IN_HEADER=$(echo "$JSON" | rg '"total_findings"' | rg -o '[0-9]+' | head -1)
assert_eq "total_findings matches count" "$TOTAL_FINDINGS" "$TOTAL_IN_HEADER"

echo ""
echo "=== Test 8: summary has correct counts ==="
assert_contains "summary has monolog-logrecord count" "$JSON" '"monolog-logrecord"'
assert_contains "summary has constructor-param-reorder count" "$JSON" '"constructor-param-reorder"'

echo ""
echo "=== Test 9: custom --paths flag works ==="
OUTPUT2=$(mktemp)
EXIT2=0
"$SCRIPT_DIR/bin/scan-risky" "$fixture_dir" "--paths=/app/code" "--output=$OUTPUT2" 2>/dev/null || EXIT2=$?
if [ "$EXIT2" -eq 0 ]; then
    echo "  ✓ scan-risky with --paths exits 0"
    PASS=$((PASS + 1))
else
    echo "  ✗ scan-risky with --paths exited with code $EXIT2"
    FAIL=$((FAIL + 1))
fi

rm -rf "$fixture_dir"
rm -f "$OUTPUT_FILE" "$OUTPUT2"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi