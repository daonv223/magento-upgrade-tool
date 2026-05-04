# Magento Upgrade Tool - Claude Code Plugin

AI-powered Magento version upgrade assistant. Analyzes codebase for upgrade incompatibilities, suggests fixes, and guides the upgrade process.

## Installation

### 1. Clone the plugin into your Magento project

From your Magento project root:

```bash
git clone <repo-url> .claude-plugin/magento-upgrade-tool
```

Or add as a git submodule:

```bash
git submodule add <repo-url> .claude-plugin/magento-upgrade-tool
```

### 2. Register the plugin in project settings

Create or edit `.claude/settings.json` in your Magento project root:

```json
{
  "plugins": [
    ".claude-plugin/magento-upgrade-tool/src"
  ]
}
```

### 3. Verify installation

Open Claude Code in your Magento project directory and run:

```
/upgrade --help
```

You should see the available commands listed below.

## Requirements

- [Claude Code](https://claude.ai/claude-code) CLI installed
- A Magento 2 project