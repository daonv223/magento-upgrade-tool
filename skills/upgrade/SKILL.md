---
description: Magento version upgrade assistant. Detects edition/version, handles composer updates, and fixes errors
argument-hint: <target-version>
allowed-tools: Bash, Read, Edit, Write
---

# Magento Upgrade

Upgrade a Magento project to a target version. Follows a strict step-by-step process.

## Arguments

- `$ARGUMENTS` — optional target version (e.g. `2.4.8-p1`). If omitted, list latest versions and ask user to choose.

## Process

### Step 1: Detect Current Setup

Read `composer.json` in the Magento project root.

```bash
cat composer.json | grep -E '"magento/product-(community|enterprise)-edition"'
```

Determine:
- **Edition**: `magento/product-community-edition` or `magento/product-enterprise-edition`
- **Current version**: the version constraint value

Report findings to user before proceeding.

### Step 2: Determine Target Version

If `$ARGUMENTS` contains a version:
- Use it as target version

If no version specified:
- List available versions:
```bash
composer show magento/product-community-edition --all 2>/dev/null | grep -m 10 versions
```
- (Use `product-enterprise-edition` if enterprise detected)
- Show latest versions to user and ask them to choose

### Step 3: Validate Target Version

Confirm target version exists in the available versions list from Step 2.

If not found, show error and ask user to pick valid version.

### Step 4: Check PHP Compatibility

```bash
php -v
```

Known requirements:
- Magento 2.4.8+: PHP 8.3 or 8.4
- Magento 2.4.7: PHP 8.2 or 8.3
- Magento 2.4.6: PHP 8.1 or 8.2

If PHP version incompatible with target, warn user and ask whether to continue.

### Step 5: Check Git Status

```bash
git status --porcelain
```

If working tree dirty, warn user: "Uncommitted changes detected. Recommend committing or stashing before upgrade."

Ask user to confirm before proceeding.

### Step 6: Download composer.phar

Always download fresh composer.phar matching current PHP:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

Verify download:
```bash
php composer.phar --version
```

### Step 7: Align require-dev with Target Version

Fetch the official `composer.json` from the target Magento version to use as reference for `require-dev` constraints:

```
https://raw.githubusercontent.com/magento/magento2/<TARGET_VERSION>/composer.json
```

For example, if target is `2.4.8-p4`:
```
https://raw.githubusercontent.com/magento/magento2/2.4.8-p4/composer.json
```

Compare the project's `require-dev` against the reference:

1. **Packages that exist in both** — update the project's constraint to match the reference version
2. **Packages in the project but NOT in the reference** — check if they have a version compatible with the target PHP. If yes, widen to `"*"`. If abandoned or no compatible version exists, inform user and ask whether to remove or find a replacement
3. **Packages in the reference but NOT in the project** — do NOT add them (keep the project's dev tooling minimal to what they already use)

Apply updates in a single command:

```bash
php composer.phar require --dev <package1>:"<ref_version1>" <package2>:"<ref_version2>" <package3>:"*" --no-update 2>&1
```

Report changes to user: what was updated to match the reference, and what was widened because no reference existed.

### Step 8: Update Composer Requirement

Based on edition detected in Step 1:

**Community edition:**
```bash
php composer.phar require magento/product-community-edition <TARGET_VERSION> --no-update
```

**Enterprise edition:**
```bash
php composer.phar require-commerce magento/product-enterprise-edition <TARGET_VERSION> --no-update
```

### Step 9: Disable Patches

If `composer.json` has a `patches` section inside `extra` (used by `cweagans/composer-patches`), disable it before running composer update — patches were written for old vendor code and will likely fail on new versions.

Rename the key to temporarily disable:

```bash
php -r '
$c = json_decode(file_get_contents("composer.json"), true);
if (isset($c["extra"]["patches"])) {
    $c["extra"]["_patches_disabled"] = $c["extra"]["patches"];
    unset($c["extra"]["patches"]);
    file_put_contents("composer.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo "Patches disabled temporarily.";
} else {
    echo "No patches section found.";
}
'
```

Report to user which patches were disabled.

### Step 10: Analyze Third-Party Packages & Resolve Conflicts

#### 10a: Run Dry Run

```bash
php composer.phar update -W --dry-run 2>&1
```

If clean (exit 0), skip to Step 11.

If errors occur, parse the output to extract conflicting packages. Common error patterns:
- `"<package> X.Y.Z requires <dep> ^N.0 but …"` — version conflict
- `"<package> is locked to version X.Y.Z and an update … is not allowed"` — lock constraint
- `"<package> requires php ^X.Y but your php version …"` — PHP incompatibility

Collect a list of all conflicting **non-Magento** packages (skip `magento/*` packages — those are handled by the framework update).

#### 10b: Check Available Versions for Each Conflicting Package

For each conflicting package, check what versions exist:

```bash
php composer.phar show <package> --all --format=json 2>/dev/null | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
echo "name: " . ($d["name"] ?? "?") . "\n";
echo "abandoned: " . (isset($d["abandoned"]) ? ($d["abandoned"] ?: "yes") : "no") . "\n";
echo "versions: " . implode(", ", array_slice($d["versions"] ?? [], 0, 10)) . "\n";
'
```

If the `--format=json` flag is not supported, fall back to:

```bash
php composer.phar show <package> --all 2>/dev/null | grep -E "^(versions|abandoned)"
```

For each package, also check its latest version's requirements:

```bash
php composer.phar show <package> <latest_version> 2>/dev/null | grep -E "^requires"
```

#### 10c: Categorize Each Package

Based on the information gathered, categorize each conflicting package:

| Category | Criteria | Action |
|----------|----------|--------|
| **Upgrade** | A newer version exists that supports the target PHP and doesn't conflict with Magento | Recommend specific version to require |
| **Widen** | Current version actually supports target but constraint is too narrow (e.g. `"1.2.3"` instead of `"^1.2"`) | Recommend widening constraint |
| **Replace** | Package is marked `abandoned` with a suggested replacement | Recommend replacement package |
| **Remove** | Package is abandoned with no replacement, or no version supports target PHP/Magento | Recommend removal (ask user to confirm it's not critical) |
| **Investigate** | Can't determine compatibility automatically (private repo, no version info) | Flag for manual review |

#### 10d: Present Recommendations

Present a table to the user:

```
┌─────────────────────────────┬───────────┬────────────┬──────────────────────────────┐
│ Package                     │ Current   │ Action     │ Recommendation               │
├─────────────────────────────┼───────────┼────────────┼──────────────────────────────┤
│ vendor/package-a            │ ^1.0      │ Upgrade    │ Require ^2.0 (supports PHP   │
│                             │           │            │ 8.3 + Magento 2.4.8)         │
│ vendor/package-b            │ 3.2.1     │ Widen      │ Change to ^3.2               │
│ vendor/package-c            │ ^1.5      │ Replace    │ Replace with vendor/new-pkg  │
│ vendor/package-d            │ ^2.0      │ Remove     │ Abandoned, no compatible ver  │
│ vendor/package-e            │ ^4.0      │ Investigate│ Private repo, check manually │
└─────────────────────────────┴───────────┴────────────┴──────────────────────────────┘
```

Ask user to confirm or adjust each action before proceeding.

#### 10e: Apply Confirmed Actions

After user confirms, apply all changes in one command where possible:

**Upgrades and widens:**
```bash
php composer.phar require <package-a>:"^2.0" <package-b>:"^3.2" --no-update 2>&1
```

**Replacements:**
```bash
php composer.phar remove <old-package> --no-update 2>&1
php composer.phar require <new-package>:"^1.0" --no-update 2>&1
```

**Removals:**
```bash
php composer.phar remove <package-d> --no-update 2>&1
```

#### 10f: Re-run Dry Run

```bash
php composer.phar update -W --dry-run 2>&1
```

If new conflicts appear, repeat from 10b for the newly conflicting packages.

Loop until dry-run is clean or user decides to stop.

### Step 11: Backup Vendor and Apply Update

Before updating, zip the current vendor folder as a rollback safety net:

```bash
zip -qr vendor-backup.zip vendor/
```

Apply the update:

```bash
php composer.phar update -W
```

**If composer update fails (non-zero exit code):**

1. Restore original state:
```bash
git checkout composer.json composer.lock
rm -rf vendor/
unzip -qo vendor-backup.zip
```

2. Report the error to user and run the same analysis as Step 10b–10d to identify and recommend fixes for conflicting packages.

3. After user confirms fixes, re-apply Steps 7–10, then retry Step 11.

**If composer update succeeds:**

Remove the backup:
```bash
rm -f vendor-backup.zip
```

### Step 12: Review and Re-enable Patches

After composer update succeeds, review each disabled patch to determine if it should be kept:

```bash
php -r '
$c = json_decode(file_get_contents("composer.json"), true);
if (isset($c["extra"]["_patches_disabled"])) {
    foreach ($c["extra"]["_patches_disabled"] as $pkg => $patches) {
        echo "$pkg:\n";
        foreach ($patches as $name => $file) {
            echo "  $name => $file\n";
        }
    }
}
'
```

For each patch:
1. Read the patch file content to understand what it fixes
2. Check if the issue is already fixed in the target version (search the new vendor code)
3. If still needed, check if the patch applies cleanly to the new vendor code:
   ```bash
   git apply --check <patch_file> 2>&1
   ```

Present findings to user:
- **Keep**: patch still needed and applies cleanly
- **Remove**: issue already fixed in target version
- **Needs update**: patch still needed but doesn't apply cleanly (file changed)

After user confirms which patches to keep, re-enable them:

```bash
php -r '
$c = json_decode(file_get_contents("composer.json"), true);
$keep = ["package/name" => ["Patch Name" => "./patches/file.patch"]]; // confirmed patches
if (!empty($keep)) {
    $c["extra"]["patches"] = $keep;
}
unset($c["extra"]["_patches_disabled"]);
file_put_contents("composer.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
```

If patches were re-enabled, run composer update again to apply them:
```bash
php composer.phar update -W
```

### Step 13: Magento Post-Upgrade

**Spawn a sub-agent** to run the post-upgrade commands. The sub-agent must loop until all three commands succeed.

The three commands must run in order — each depends on the previous:

```
1. bin/magento setup:upgrade
2. bin/magento setup:di:compile
3. bin/magento setup:static-content:deploy -f
```

**Sub-agent error-handling loop:**

For each command, run it and check the exit code. If it fails:

1. **Parse the error output** to identify the problematic module/class.

2. **Attempt a simple fix first** — if the error is straightforward (e.g. missing return type, incompatible type hint, undefined constant, missing `use` import, deprecated function call), fix the specific broken line(s) in the module code. Only fix what the error message points to — do not refactor or fix unrelated code.

3. **If the fix is not simple** (e.g. deep architectural incompatibility, missing dependency, class completely removed upstream), **temporarily disable the module** by renaming its `registration.php`:
   ```bash
   mv <module_path>/registration.php <module_path>/registration.php.bak
   ```

4. **Re-run the same command** after either fixing or disabling. Repeat until the command passes.

5. **Move to the next command** only after the current one succeeds.

The sub-agent must keep a running list of:
- **Modules fixed**: what was changed and why
- **Modules disabled**: path and reason

After all three commands pass, the sub-agent reports back with the full list of fixes and disabled modules so the user can review them.

### Step 14: Verify

```bash
bin/magento --version
```

Report final version to user. Upgrade complete.

## Error Handling

When composer errors occur:
1. Read full error output
2. Identify root cause (version conflict, missing extension, etc.)
3. Propose fix to user
4. Apply fix after user confirms
5. Re-run failed step

Do NOT silently skip errors. Always surface them to user.

## Important Notes

- Always use `php composer.phar` (not system composer)
- Always use correct edition package name throughout
- Never force or bypass version constraints without user approval
- Clean up `composer-setup.php` after download
