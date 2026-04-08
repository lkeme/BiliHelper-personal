# Plugin Manifest Links Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `activity_url` and `reference_links` as formal plugin manifest metadata fields, then backfill all existing plugins with empty defaults.

**Architecture:** Keep the change fully inside manifest schema, validation, bundled plugin metadata, and developer-facing plugin scaffolding/docs. Do not route these fields into scheduler logic, runtime behavior, user config generation, or console display. Existing plugins should all end up with the same manifest shape, while values remain empty for later manual filling.

**Tech Stack:** PHP 8.5, JSON plugin manifests, PHPUnit 12, existing plugin manifest DTO/validator pipeline

---

## File Map

### Modify

- `src/Plugin/PluginManifest.php`
  - Add typed support for `activity_url` and `reference_links`
- `src/Plugin/PluginManifestValidator.php`
  - Validate new fields with structural and minimal content rules
- `src/Plugin/CorePluginRegistry.php`
  - Add the new fields to the built-in `Login` manifest
- `docs/PLUGIN_GUIDE.md`
  - Add a short manifest example and field explanation
- `plugins/*/plugin.json`
  - Backfill `activity_url` and `reference_links` with empty defaults

### Create

- `tests/Plugin/PluginManifestTest.php`
  - DTO normalization tests for new fields
- `tests/Plugin/PluginManifestValidatorTest.php`
  - Validator coverage for required shape and allowed empty defaults

### Optional follow-up if a template file exists during implementation

- The plugin template/scaffold manifest file used for new plugins

---

### Task 1: Extend PluginManifest DTO

**Files:**
- Modify: `src/Plugin/PluginManifest.php`
- Test: `tests/Plugin/PluginManifestTest.php`

- [ ] **Step 1: Write the failing DTO normalization test**

Add coverage for:
- `activity_url` round-trips as a string
- `reference_links` round-trips as `[{url, comment}]`
- empty defaults remain `""` and `[]`

- [ ] **Step 2: Run the DTO test to verify it fails**

Run:

```bash
vendor\bin\phpunit tests\Plugin\PluginManifestTest.php
```

Expected: FAIL because the DTO does not yet expose the new fields.

- [ ] **Step 3: Implement minimal DTO support**

Update `PluginManifest` to:
- accept `activityUrl` and `referenceLinks` as constructor fields
- include them in `fromArray()`
- include them in `toArray()`
- treat them as known manifest keys rather than `extra`

- [ ] **Step 4: Run the DTO test to verify it passes**

Run:

```bash
vendor\bin\phpunit tests\Plugin\PluginManifestTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Plugin/PluginManifest.php tests/Plugin/PluginManifestTest.php
git commit -m "feat: add manifest link metadata fields"
```

---

### Task 2: Extend PluginManifestValidator

**Files:**
- Modify: `src/Plugin/PluginManifestValidator.php`
- Test: `tests/Plugin/PluginManifestValidatorTest.php`

- [ ] **Step 1: Write the failing validator tests**

Cover these cases:
- valid manifest with `activity_url=""` and `reference_links=[]`
- invalid manifest when `activity_url` is missing or non-string
- invalid manifest when `reference_links` is missing or non-array
- invalid manifest when a reference item lacks `url` or `comment`
- invalid manifest when `reference_links[*].url` is `""`
- valid manifest when `reference_links[*].comment` is `""`

- [ ] **Step 2: Run the validator tests to verify they fail**

Run:

```bash
vendor\bin\phpunit tests\Plugin\PluginManifestValidatorTest.php
```

Expected: FAIL because validator does not yet know the new schema.

- [ ] **Step 3: Implement minimal validator changes**

Update `PluginManifestValidator` to:
- require the two new fields
- validate only structure and minimal content
- allow wildcard-like URLs without strict URL parsing

- [ ] **Step 4: Run the validator tests to verify they pass**

Run:

```bash
vendor\bin\phpunit tests\Plugin\PluginManifestValidatorTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Plugin/PluginManifestValidator.php tests/Plugin/PluginManifestValidatorTest.php
git commit -m "feat: validate manifest link metadata"
```

---

### Task 3: Backfill Built-In Login Manifest

**Files:**
- Modify: `src/Plugin/CorePluginRegistry.php`
- Test: `tests/Plugin/PluginManifestTest.php`

- [ ] **Step 1: Add a failing assertion for Login manifest defaults**

Extend existing or new test coverage so the built-in `Login` manifest is expected to expose:

```php
'activity_url' => ''
'reference_links' => []
```

- [ ] **Step 2: Run targeted tests to verify failure**

Run:

```bash
vendor\bin\phpunit tests\Plugin\PluginManifestTest.php
```

Expected: FAIL because the built-in manifest is missing the fields.

- [ ] **Step 3: Add the new fields to the Login manifest**

Update `CorePluginRegistry` only. Do not change runtime behavior.

- [ ] **Step 4: Re-run the targeted test**

Run:

```bash
vendor\bin\phpunit tests\Plugin\PluginManifestTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Plugin/CorePluginRegistry.php tests/Plugin/PluginManifestTest.php
git commit -m "chore: backfill login manifest link metadata"
```

---

### Task 4: Backfill All Bundled Plugin Manifests

**Files:**
- Modify: `plugins/*/plugin.json`

- [ ] **Step 1: Identify all bundled plugin manifest files**

Use:

```bash
rg --files plugins -g "plugin.json"
```

Expected: list of every bundled plugin manifest.

- [ ] **Step 2: Add empty defaults to every manifest**

Each manifest should explicitly contain:

```json
"activity_url": "",
"reference_links": []
```

Do not attempt to fill real URLs in this task.

- [ ] **Step 3: Run a manifest load/validation check**

Run the narrowest available command that exercises plugin discovery and validation, or a small PHP test harness if needed.

Expected: all manifests remain loadable after the schema extension.

- [ ] **Step 4: Commit**

```bash
git add plugins/*/plugin.json
git commit -m "chore: backfill plugin manifest link metadata"
```

---

### Task 5: Update Template and Developer Guide

**Files:**
- Modify: template/scaffold manifest file if present
- Modify: `docs/PLUGIN_GUIDE.md`

- [ ] **Step 1: Locate the plugin template manifest source**

Search for the scaffold/template used when creating a new plugin.

- [ ] **Step 2: Add default fields to the template**

Template output must include:

```json
"activity_url": "",
"reference_links": []
```

- [ ] **Step 3: Add a short guide section**

In `docs/PLUGIN_GUIDE.md`, add:
- field purpose
- empty-default rule
- one short manifest example

- [ ] **Step 4: Run syntax/smoke checks**

Run:

```bash
php -l src/Plugin/PluginManifest.php
php -l src/Plugin/PluginManifestValidator.php
php -l src/Plugin/CorePluginRegistry.php
vendor\bin\phpunit tests/Plugin/PluginManifestTest.php tests/Plugin/PluginManifestValidatorTest.php
```

Expected: all pass

- [ ] **Step 5: Commit**

```bash
git add docs/PLUGIN_GUIDE.md [template-file]
git commit -m "docs: document manifest link metadata"
```

---

### Task 6: Final Verification

**Files:**
- Verify only

- [ ] **Step 1: Run focused PHPUnit coverage**

```bash
vendor\bin\phpunit tests/Plugin/PluginManifestTest.php tests/Plugin/PluginManifestValidatorTest.php
```

Expected: PASS

- [ ] **Step 2: Run PHP syntax checks**

```bash
php -l src/Plugin/PluginManifest.php
php -l src/Plugin/PluginManifestValidator.php
php -l src/Plugin/CorePluginRegistry.php
```

Expected: all report `No syntax errors detected`

- [ ] **Step 3: Inspect git diff**

```bash
git status --short
git diff --stat
```

Expected:
- only schema/docs/template/plugin-manifest files changed
- no unrelated runtime logic touched

- [ ] **Step 4: Final commit if needed**

If any verification-only fixes were required, commit them with a narrow message.
