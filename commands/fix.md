---
description: Scan a Magento path for upgrade issues and offer AI-powered fixes
argument-hint: <path>
model: claude-sonnet-4-6
---

# Magento Fix Command

Scan a Magento project path for context-dependent upgrade issues, then offer to fix them with parallel AI subagents.

## Arguments

- `$ARGUMENTS` — relative path inside the Magento project (e.g. `app/code/Betanet` or `app/code/Betanet/MyModule`).

## Step 1: Auto-detect Magento Project

1. Start from the current working directory (`pwd`).
2. Walk upward parent directories until you find `bin/magento`.
3. If found, store that directory as `MAGENTO_ROOT`.
4. If you reach the filesystem root without finding `bin/magento`, stop and report: *"No Magento project detected. Please run this command from inside a Magento project."*

## Step 2: Normalize Scan Path

Take `$ARGUMENTS`:
- Strip any leading `./`.
- If it does **not** start with `/`, prepend `/`.
- Store as `SCAN_PATH`.

Example: `app/code/Betanet` → `/app/code/Betanet`.

## Step 3: Run Problem Scanner

Execute the bundled scanner (relative to this plugin root):

```bash
./autofixer/bin/scan-problems "$MAGENTO_ROOT" --paths="$SCAN_PATH"
```

Wait for it to finish. Note the output file path printed at the end (usually `$MAGENTO_ROOT/reports/risky-findings-<sanitized-path>.json`).

If the scanner exits with an error, report the error output and stop.

## Step 4: Read and Summarize Report

Read the JSON report file.

Parse these fields:
- `totals.file_errors` — total findings.
- `files` — object keyed by module name. Each value has:
  - `errors` — count for that module.
  - `files` — object keyed by absolute file path. Each value has `messages` (array of `{message, line, identifier}`).

Present a concise summary to the user:
1. **Total findings**: `totals.file_errors`.
2. **Modules affected**: list each module with its error count.
3. **Sample issues**: show 2–3 representative errors (file, line, message).

## Step 5: Ask for Confirmation

Use the `question` tool to ask:

> **Found X issue(s) across Y module(s).**
>
> Do you want to spawn AI agents to fix them? Each module will be handled by a dedicated agent.
>
> - **Yes** — spawn agents now
> - **No** — stop here

If the user chooses **No**, stop.

## Step 6: Spawn Fix Agents

For each module in the report, prepare a focused fix task.

**Parallelism rules:**
- If there are **≤ 5 modules**, spawn one agent per module (all in parallel).
- If there are **> 5 modules**, group them into at most 5 batches and spawn one agent per batch.

For each agent, use the `Task` tool with `subagent_type: general` and reference the `magento-fixer` agent for its specialized knowledge.

**Agent prompt template:**

```
Run as magento-fixer agent.

Module: <MODULE_NAME>
Magento root: <MAGENTO_ROOT>

Issues to fix:
<LIST_OF_FILES_AND_MESSAGES>
```

The `magento-fixer` agent will:
1. Read each affected file.
2. Apply minimal, correct fixes per its built-in rules (return.missing, class.notFound for Zend, property.notFound, etc.).
3. Verify syntax with `php -l <file>` after each edit.
4. Report back what was changed and what was skipped.

Launch all agents in parallel. Wait for every agent to report back.

## Step 7: Report Final Results

After all agents finish, summarize for the user:

1. **Modules processed**: count.
2. **Files modified**: total count.
3. **Changes made**: bullet-point summary per module (from agent reports).
4. **Issues skipped**: list any that agents could not fix and why.
5. **Next steps**:
   - Run `bin/magento setup:di:compile` to verify.
   - Run the scanner again to confirm issues are resolved.

End with: *"Upgrade fixes complete. Review the changes before committing."*
