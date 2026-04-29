---
description: Review all 3rd-party modules in app/code, scan for problems, and generate an xlsx report
allowed-tools: Bash, Read, Write, Edit
---

# Review 3rd-Party Modules

Scan all third-party modules in `app/code/` for upgrade problems and produce an Excel report.

## Arguments

- `$ARGUMENTS` — (optional) path to Magento root. If omitted, auto-detect.

## Step 1: Detect Magento Root and Plugin Root

**Plugin root**: This plugin is installed at a fixed location. Resolve it by finding the directory containing this command file. The scanner and parser tools live under the plugin root:
- Scanner: `<PLUGIN_ROOT>/autofixer/bin/scan-problems`
- Parser: `<PLUGIN_ROOT>/autofixer/bin/parse-report`

To find the plugin root, search for the `autofixer/bin/scan-problems` file:
```bash
find ~/.claude/plugins -path "*/magento-upgrade-tool/autofixer/bin/scan-problems" -exec dirname {} \; 2>/dev/null | head -1 | sed 's|/autofixer/bin||'
```
If not found there, also check common locations:
```bash
find ~/Sites -maxdepth 4 -path "*/upgrade-tool/src/autofixer/bin/scan-problems" -exec dirname {} \; 2>/dev/null | head -1 | sed 's|/autofixer/bin||'
```
Store as `PLUGIN_ROOT`.

**Magento root**: If `$ARGUMENTS` is provided, use it as `MAGENTO_ROOT`. Otherwise, start from `pwd` and walk upward until you find `bin/magento`. Store that directory as `MAGENTO_ROOT`. If not found, stop and report: *"No Magento project detected."*

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

## Step 4: Scan Each Module

For each accepted vendor, list its modules:

```bash
ls -d "$MAGENTO_ROOT"/app/code/<Vendor>/*/ | xargs -n1 basename
```

For each module `<Vendor>_<Module>`, run the problem scanner:

```bash
"$PLUGIN_ROOT/autofixer/bin/scan-problems" "$MAGENTO_ROOT" --paths="/app/code/<Vendor>/<Module>" 2>&1
```

This produces a JSON report at `$MAGENTO_ROOT/reports/risky-findings-app-code-<Vendor>-<Module>.json`.

After all scans complete, parse all reports at once using the bundled parser:

```bash
python3 "$PLUGIN_ROOT/autofixer/bin/parse-report" "$MAGENTO_ROOT"/reports/risky-findings-app-code-*.json
```

This outputs JSON with `module`, `errors`, and `types` (unique problem identifiers) per module.

If the scanner fails for a module, skip it and continue to the next module.

## Step 5: Generate XLSX Report

Use the **xlsx skill** to create the report file at `MAGENTO_ROOT/reports/3rd-party-modules-review.xlsx`.

Follow the xlsx skill guidelines for creating Excel files with openpyxl. Use formulas (not hardcoded values) for totals.

### Tab: "3rd Party Modules"

| Column | Content |
|--------|---------|
| A | Module (e.g. `Amasty_Promo`) |
| B | Number of Problems |
| C | Problem Types — comma-separated list of unique `identifier` values (e.g. `return.missing, class.notFound, property.notFound`) |

Requirements:
- Header row: bold, with background color `#4472C4` and white text, font Arial
- Auto-filter enabled on all columns
- Column A width: 40
- Column B width: 20, center-aligned
- Column C width: 60, wrap text enabled
- Sort rows by number of problems descending
- Add a total row at the bottom: "TOTAL" in column A, `=SUM(B2:B<last>)` formula in column B, bold
- After saving, recalculate formulas using `python scripts/recalc.py` from the xlsx skill

## Step 6: Report to User

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
