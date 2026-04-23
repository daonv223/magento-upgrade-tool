---
name: upgrade
description: >
  Magento version upgrade assistant. Detects current edition and version,
  validates target version, handles composer updates, and fixes errors.
  Use when user says "upgrade magento", "update magento version",
  or invokes /upgrade. Accepts optional target version argument like /upgrade 2.4.8-p1.
tools: Bash, Read, Edit, Write
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

### Step 7: Clean Incompatible require-dev Packages

Before updating, scan `require-dev` for packages that won't work with the target PHP version. Remove them all in one batch.

```bash
php -r '$c=json_decode(file_get_contents("composer.json"),true); foreach($c["require-dev"] ?? [] as $k=>$v) echo "$k: $v\n";'
```

Check each package's PHP compatibility. Common incompatible packages in older Magento projects:
- `phpunit/phpunit` with old versions (~6.x, ~7.x) — requires PHP 7.x
- `magento/magento2-functional-testing-framework` old versions — requires PHP 7.x
- `allure-framework/allure-phpunit` old versions — depends on old phpunit
- `friendsofphp/php-cs-fixer` old versions (~2.x) — requires PHP 7.x
- `sebastian/phpcpd` old versions (~3.x) — requires PHP 7.x
- `lusitanian/oauth` old versions — requires PHP 7.x
- `pdepend/pdepend` old pinned versions — requires PHP 7.x
- `phpmd/phpmd` old versions — may conflict with newer dependencies

Remove all incompatible ones in a single command:

```bash
php composer.phar remove <package1> <package2> <package3> --dev --no-update --no-plugins 2>&1
```

Report what was removed to user.

### Step 8: Update Composer Requirement

Try `require-commerce` first, fallback to `require`:

```bash
php composer.phar require-commerce magento/product-community-edition <TARGET_VERSION> --no-update --no-plugins
```

If that fails:
```bash
php composer.phar require magento/product-community-edition <TARGET_VERSION> --no-update --no-plugins
```

Use the correct edition package name detected in Step 1.

### Step 9: Dry Run

```bash
php composer.phar update --no-plugins --dry-run 2>&1
```

If errors about dependency upgrades needed (e.g. "package X requires Y ^2.0 but root requires ^1.0"):
- Tell user which third-party modules need upgrading to be compatible with the target Magento version
- List each module with its current constraint and what version is needed
- Give user two options:
  1. Upgrade those modules to compatible versions manually, then retry
  2. Add `-W` flag to allow composer to automatically upgrade/downgrade/remove dependencies
- Do NOT automatically remove or change constraints on third-party modules

If errors about PHP incompatibility in `require` packages:
- Tell user which packages need PHP-compatible versions
- Ask user to decide: upgrade the package or remove it

Loop until clean or user decides to stop.

### Step 10: Apply Update

```bash
php composer.phar update --no-plugins
```

If errors occur, use same approach as Step 9 — tell user which modules need upgrading.

### Step 10: Magento Post-Upgrade

Run sequentially, stop on first error and attempt fix:

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

### Step 11: Verify

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
