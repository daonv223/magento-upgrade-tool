---
description: Review all 3rd-party modules in app/code, scan for problems, and generate an xlsx report
allowed-tools: Bash, Read, Write, Edit
---

# Review 3rd-Party Modules

Scan all third-party modules in `app/code/` for upgrade problems and produce an Excel report.

## Arguments

- `$ARGUMENTS` — (optional) path to Magento root. If omitted, auto-detect.

## Step 1: Detect Magento Root

If `$ARGUMENTS` is provided, use it as `MAGENTO_ROOT`. Otherwise, start from `pwd` and walk upward until you find `bin/magento`. Store that directory as `MAGENTO_ROOT`. If not found, stop and report: *"No Magento project detected."*

## Step 2: List Vendors

List all vendor directories inside `MAGENTO_ROOT/app/code/`:

```bash
ls -d "$MAGENTO_ROOT"/app/code/*/ | xargs -n1 basename | sort
```

Present the numbered list to the user.

## Step 3: Ask for Exclusions

Ask the user:

> **Found N vendor(s) in app/code/.**
>
> Enter vendor names or numbers to **exclude** (comma-separated), or press Enter to include all.

Remove excluded vendors from the list. The remaining vendors are `ACCEPTED_VENDORS`.

## Step 4: Clean Old Reports

Remove any previous scan reports to avoid stale data:

```bash
rm -f "$MAGENTO_ROOT"/reports/risky-findings-app-code-*.json
rm -f "$MAGENTO_ROOT"/reports/3rd-party-modules-review.xlsx
```

## Step 5: Scan Each Module

For each accepted vendor, list its modules:

```bash
ls -d "$MAGENTO_ROOT"/app/code/<Vendor>/*/ | xargs -n1 basename
```

Run all module scans in parallel (up to 5 concurrent) using background processes:

```bash
for module_path in "$MAGENTO_ROOT"/app/code/<Vendor>/*/; do
  module=$(basename "$module_path")
  scripts/scan-problems "$MAGENTO_ROOT" --paths="/app/code/<Vendor>/$module" 2>&1 &
  # Limit to 5 concurrent jobs
  while [ "$(jobs -r | wc -l)" -ge 5 ]; do sleep 1; done
done
wait
```

Each scan produces a JSON report at `$MAGENTO_ROOT/reports/risky-findings-app-code-<Vendor>-<Module>.json`.

After all scans complete, parse all reports at once using the bundled parser:

```bash
python3 scripts/parse-report "$MAGENTO_ROOT"/reports/risky-findings-app-code-*.json
```

This outputs JSON with `module`, `errors`, and `types` (unique problem identifiers) per module.

If a scanner fails for a module, skip it and continue.

## Step 6: Classify Each Module

**Spawn the `module-classifier` agent** (uses Sonnet) to classify all modules in parallel.

Pass to the agent:
- `$MAGENTO_ROOT` — the Magento project root
- `$MODULES` — "all" (or list specific modules if only a subset was scanned)
- `$CLIENT_VENDOR` — ask the user which vendor name(s) are their own in-house modules (e.g. "Betanet,Fisha")
- `$SCAN_RESULTS` — `$MAGENTO_ROOT/reports/`

The agent runs obfuscation checks and origin detection for all modules concurrently, reads the scan reports from Step 5, applies the decision logic, and returns a JSON array with `module`, `errors`, `need_upgrade`, and `comment` for each module.

Wait for the agent to complete before proceeding to Step 7.

## Step 7: Generate XLSX Report

Use the **xlsx skill** to create the report file at `MAGENTO_ROOT/reports/3rd-party-modules-review.xlsx`.

Follow the xlsx skill guidelines for creating Excel files with openpyxl. Use formulas (not hardcoded values) for totals.

### Tab: "3rd Party Modules"

| Column | Content |
|--------|---------|
| A | Module (e.g. `Amasty_Promo`) |
| B | Number of Problems |
| C | Need to upgrade? — "Yes", "No", or blank (if 0 errors) |
| D | Comments — reason for the decision (e.g. "Encrypted code — must get new version from vendor", "Low error count (5), all auto-fixable — fix in place", "In-house module — fix in place") |

Requirements:
- Header row: bold, with background color `#4472C4` and white text, font Arial
- Auto-filter enabled on all columns
- Column A width: 40
- Column B width: 20, center-aligned
- Column C width: 20, center-aligned
- Column D width: 60, wrap text enabled
- Sort rows by number of problems descending
- Add a total row at the bottom: "TOTAL" in column A, `=SUM(B2:B<last>)` formula in column B, bold
- After saving, recalculate formulas using the xlsx skill's recalc script

## Step 8: Report to User

Tell the user:

> **Review complete.**
>
> - Vendors scanned: N
> - Modules scanned: M
> - Total problems found: X
> - Report saved to: `MAGENTO_ROOT/reports/3rd-party-modules-review.xlsx`
>
> Top modules by problem count:
> 1. Module_Name — X problems
> 2. ...
> (show top 10)
