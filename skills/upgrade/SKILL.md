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

If target version >= 2.4.8, also add the Amasty compatibility fix:
```bash
php composer.phar require amasty/module-mage-248-fix --no-update
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

### Step 10: Dry Run

```bash
php composer.phar update -W --dry-run 2>&1
```

If errors about dependency upgrades needed (e.g. "package X requires Y ^2.0 but root requires ^1.0"):
- Tell user which third-party modules need upgrading to be compatible with the target Magento version
- List each module with its current constraint and what version is needed
- Do NOT automatically remove or change constraints on third-party modules

If errors about PHP incompatibility in `require` packages:
- Tell user which packages need PHP-compatible versions
- Ask user to decide: upgrade the package or remove it

Loop until clean or user decides to stop.

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

2. Report the error to user and use same approach as Step 10 — tell user which modules need upgrading.

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
