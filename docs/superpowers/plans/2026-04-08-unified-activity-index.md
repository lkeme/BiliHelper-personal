# Unified Activity Index Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Python-based unified activity index generator that outputs four remote resource files and adapt `ActivityLottery`, `Lottery`, and `LiveReservation` to consume those files within the boundaries defined by the approved spec.

**Architecture:** The implementation has four layers: Python collection/parsing under `tools/activity_index/`, generated `resources/*.json` artifacts, a GitHub Actions workflow that refreshes those artifacts on schedule, and plugin-side remote adapters that consume only their own file and still retain critical runtime parsing/API confirmation on the user side. Existing PHP `ActivityInfoUpdate` remains as a local helper and is not part of the main automatic path.

**Tech Stack:** Python 3 with `pyproject.toml`, GitHub Actions, existing PHP plugin/runtime infrastructure, existing `RemoteResourceResolver` usage, temporary local verification scripts that must be deleted before commit.

---

### Task 1: Scaffold the Python Activity Index Project

**Files:**
- Create: `tools/activity_index/pyproject.toml`
- Create: `tools/activity_index/main.py`
- Create: `tools/activity_index/collectors/__init__.py`
- Create: `tools/activity_index/parsers/__init__.py`
- Create: `tools/activity_index/models/__init__.py`
- Create: `tools/activity_index/writers/__init__.py`
- Create: `tools/activity_index/tests/__init__.py`
- Test: `tools/activity_index/tests/test_bootstrap.py`

- [ ] **Step 1: Write the failing bootstrap test**

Create `tools/activity_index/tests/test_bootstrap.py` that imports the planned package entrypoint and asserts the project can build an empty output envelope for all four resource files.

```python
from activity_index.main import build_empty_payloads

def test_build_empty_payloads_has_four_files():
    payloads = build_empty_payloads()
    assert sorted(payloads.keys()) == [
        "activity_lottery_infos.json",
        "charge_lottery_infos.json",
        "interactive_lottery_infos.json",
        "reservation_lottery_infos.json",
    ]
```

- [ ] **Step 2: Run the bootstrap test to verify it fails**

Run: `python -m pytest tools/activity_index/tests/test_bootstrap.py -q`
Expected: import/module failure because the Python project does not exist yet.

- [ ] **Step 3: Implement the minimal project scaffold**

Create the Python project skeleton with:

- `pyproject.toml`
- importable package layout
- `main.py` exposing `build_empty_payloads()`

Keep the first pass minimal: only enough code to satisfy the bootstrap test.

- [ ] **Step 4: Re-run the bootstrap test to verify it passes**

Run: `python -m pytest tools/activity_index/tests/test_bootstrap.py -q`
Expected: PASS.

- [ ] **Step 5: Commit the scaffold**

```bash
git add tools/activity_index
git commit -m "feat: scaffold unified activity index project"
```

### Task 2: Implement Dynamic Discovery and Article Classification

**Files:**
- Create: `tools/activity_index/collectors/space_dynamic.py`
- Create: `tools/activity_index/parsers/article_classifier.py`
- Create: `tools/activity_index/models/article.py`
- Test: `tools/activity_index/tests/test_article_classifier.py`

- [ ] **Step 1: Write the failing classifier test**

Create `tools/activity_index/tests/test_article_classifier.py` with fixtures covering the approved title prefixes:

- `活动抽奖圆盘抽奖`
- `互动抽奖`
- `预约抽奖`
- `充电抽奖`

The test should assert title-first classification and reject unknown prefixes.

```python
from activity_index.parsers.article_classifier import classify_article_title

def test_classify_known_prefixes():
    assert classify_article_title("活动抽奖圆盘抽奖【2026-04-08】") == "activity_lottery"
    assert classify_article_title("互动抽奖【2026-04-10开奖】") == "interactive_lottery"
```

- [ ] **Step 2: Run the classifier test to verify it fails**

Run: `python -m pytest tools/activity_index/tests/test_article_classifier.py -q`
Expected: failure because classifier code does not exist yet.

- [ ] **Step 3: Implement discovery and classification**

Add:

- recent-24h article discovery from the target space feed
- title-prefix classification
- a validation boundary that allows later body-content checks to reject mismatches

At this step, body validation can still be stubbed, but the classification API shape must be final.

- [ ] **Step 4: Re-run the classifier test to verify it passes**

Run: `python -m pytest tools/activity_index/tests/test_article_classifier.py -q`
Expected: PASS.

- [ ] **Step 5: Commit the classifier layer**

```bash
git add tools/activity_index/collectors tools/activity_index/parsers tools/activity_index/models
git commit -m "feat: add activity article discovery and classification"
```

### Task 3: Implement the Four Output Schemas and Writers

**Files:**
- Create: `tools/activity_index/models/activity_lottery.py`
- Create: `tools/activity_index/models/interactive_lottery.py`
- Create: `tools/activity_index/models/reservation_lottery.py`
- Create: `tools/activity_index/models/charge_lottery.py`
- Create: `tools/activity_index/writers/json_writer.py`
- Test: `tools/activity_index/tests/test_output_payloads.py`

- [ ] **Step 1: Write the failing payload test**

Create `tools/activity_index/tests/test_output_payloads.py` asserting:

- shared envelope: `code`, `remarks`, `data`
- `activity_lottery_infos.json` contains compatibility-first fields:
  - `title`, `url`, `page_id`, `activity_id`, `lottery_id`, `start_time`, `end_time`, `source_cv_id`, `source_url`, `publish_time`, `update_time`
- `interactive_lottery_infos.json` contains the approved minimum fields
- `reservation_lottery_infos.json` contains the approved minimum fields
- no schema includes `status`

- [ ] **Step 2: Run the payload test to verify it fails**

Run: `python -m pytest tools/activity_index/tests/test_output_payloads.py -q`
Expected: failure because the models/writer do not yet exist.

- [ ] **Step 3: Implement the schema models and writer**

Add normalized model builders and a JSON writer that outputs:

- `resources/activity_lottery_infos.json`
- `resources/interactive_lottery_infos.json`
- `resources/reservation_lottery_infos.json`
- `resources/charge_lottery_infos.json`

Important:

- do not include a `status` field
- `activity_lottery_infos.json` should remain close to the current `activity_infos.json` structure

- [ ] **Step 4: Re-run the payload test to verify it passes**

Run: `python -m pytest tools/activity_index/tests/test_output_payloads.py -q`
Expected: PASS.

- [ ] **Step 5: Commit the schema layer**

```bash
git add tools/activity_index/models tools/activity_index/writers tools/activity_index/tests
git commit -m "feat: add unified activity index schemas"
```

### Task 4: Implement Type-Specific Parsers with the Approved Boundaries

**Files:**
- Create: `tools/activity_index/parsers/activity_lottery_parser.py`
- Create: `tools/activity_index/parsers/interactive_lottery_parser.py`
- Create: `tools/activity_index/parsers/reservation_lottery_parser.py`
- Create: `tools/activity_index/parsers/charge_lottery_parser.py`
- Create: `tools/activity_index/tests/test_type_parsers.py`

- [ ] **Step 1: Write the failing parser tests**

Create `tools/activity_index/tests/test_type_parsers.py` with fixture-driven tests asserting:

- `ActivityLottery` parser can produce the compatibility fields required by the existing plugin
- `Lottery` parser produces structured hints only, not final execution payloads
- `LiveReservation` parser produces structured hints only, not final execution payloads
- `Charge` parser still emits quality output despite having no consuming plugin yet

Include explicit assertions that:

- `Lottery` output does not pretend to be final reserve payload
- `LiveReservation` output does not pretend to bypass plugin-side confirmation

- [ ] **Step 2: Run the parser tests to verify they fail**

Run: `python -m pytest tools/activity_index/tests/test_type_parsers.py -q`
Expected: missing-parser failures.

- [ ] **Step 3: Implement the parsers**

Implement:

- `ActivityLottery` parser using the current compatible structure
- `Lottery` parser using the reference boundary from `reference/bilibili-API-collect-trunk/docs/dynamic/detail.md`
- `LiveReservation` parser producing reservation hints
- `Charge` parser producing a same-quality future-facing structure

Important:

- keep user-side critical steps on the plugin side
- remote outputs are structured hints, not action replay payloads

- [ ] **Step 4: Re-run the parser tests to verify they pass**

Run: `python -m pytest tools/activity_index/tests/test_type_parsers.py -q`
Expected: PASS.

- [ ] **Step 5: Commit the parser layer**

```bash
git add tools/activity_index/parsers tools/activity_index/tests
git commit -m "feat: add type-specific activity index parsers"
```

### Task 5: Add the GitHub Actions Refresh Workflow

**Files:**
- Create: `.github/workflows/activity-index-refresh.yml`
- Test: `tools/activity_index/tests/test_workflow_contract.py`

- [ ] **Step 1: Write the failing workflow contract test**

Create `tools/activity_index/tests/test_workflow_contract.py` that asserts the workflow file contains:

- `workflow_dispatch`
- two cron triggers corresponding to `00:40` and `12:40` Asia/Shanghai
- checkout, Python setup, install, generator run
- commit-on-change behavior
- fixed commit message `chore: refresh remote activity indexes`

- [ ] **Step 2: Run the workflow contract test to verify it fails**

Run: `python -m pytest tools/activity_index/tests/test_workflow_contract.py -q`
Expected: failure because the workflow file does not exist yet.

- [ ] **Step 3: Implement the workflow**

Create `.github/workflows/activity-index-refresh.yml` with:

- scheduled runs
- manual dispatch
- generation command
- change detection
- commit/push only on changes

Use the current repository as the execution home. Do not introduce a second repo workflow.

- [ ] **Step 4: Re-run the workflow contract test to verify it passes**

Run: `python -m pytest tools/activity_index/tests/test_workflow_contract.py -q`
Expected: PASS.

- [ ] **Step 5: Commit the workflow**

```bash
git add .github/workflows/activity-index-refresh.yml tools/activity_index/tests/test_workflow_contract.py
git commit -m "feat: add unified activity index refresh workflow"
```

### Task 6: Adapt `ActivityLottery` to the New File Name

**Files:**
- Modify: `plugins/ActivityLottery/src/ActivityLotteryPlugin.php`
- Modify: `resources/activity_infos.json` (delete or rename as part of migration)
- Test: `tools/tmp-activity-lottery-file-check.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing file-name check**

Create `tools/tmp-activity-lottery-file-check.php` asserting:

- `ActivityLotteryPlugin` no longer refers to `activity_infos.json`
- it now refers to `activity_lottery_infos.json`

- [ ] **Step 2: Run the check to verify it fails**

Run: `rtk php tools/tmp-activity-lottery-file-check.php`
Expected: failure because the old name is still referenced.

- [ ] **Step 3: Rename the resource and update plugin wiring**

Modify `ActivityLottery` to:

- load local `resources/activity_lottery_infos.json`
- fetch remote `activity_lottery_infos.json`
- remove dependency on the old filename

Do not preserve compatibility fallback to `activity_infos.json`.

- [ ] **Step 4: Re-run the check to verify it passes**

Run: `rtk php tools/tmp-activity-lottery-file-check.php`
Expected: PASS.

- [ ] **Step 5: Remove the temporary check and commit**

```bash
git add plugins/ActivityLottery/src/ActivityLotteryPlugin.php resources/activity_lottery_infos.json
git commit -m "refactor: rename ActivityLottery remote index file"
```

Before commit:

- delete `tools/tmp-activity-lottery-file-check.php`
- ensure no temporary script remains staged

### Task 7: Adapt `Lottery` to the Remote Interactive Index

**Files:**
- Modify: `plugins/Lottery/src/LotteryPlugin.php`
- Modify: `plugins/Lottery/src/LotteryRuntimeState.php`
- Modify: `plugins/Lottery/src/LotteryDiscoveryService.php`
- Modify: `plugins/Lottery/src/LotteryQueueCoordinator.php`
- Add: `plugins/Lottery/src/LotteryRemoteIndexLoader.php`
- Test: `tools/tmp-lottery-remote-index-check.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing `Lottery` remote-index check**

Create a temporary script asserting the plugin now has a remote-index loading path and no longer relies on article scraping as the primary source.

- [ ] **Step 2: Run the check to verify it fails**

Run: `rtk php tools/tmp-lottery-remote-index-check.php`
Expected: failure because `Lottery` is still article/dynamic-discovery first.

- [ ] **Step 3: Implement `Lottery` remote-index consumption**

Adapt `Lottery` to:

- load `interactive_lottery_infos.json`
- treat it as the primary discovery source
- still perform plugin-side dynamic detail confirmation / validation before final execution
- avoid turning the remote file into an execution replay payload

Remove the old article-scraping path if it no longer serves the approved design.

- [ ] **Step 4: Re-run the check to verify it passes**

Run: `rtk php tools/tmp-lottery-remote-index-check.php`
Expected: PASS.

- [ ] **Step 5: Remove the temporary check and commit**

```bash
git add plugins/Lottery
git commit -m "refactor: drive Lottery from interactive remote index"
```

Before commit:

- delete `tools/tmp-lottery-remote-index-check.php`
- ensure no temporary script remains staged

### Task 8: Adapt `LiveReservation` to Remote Index + Local `vmids`

**Files:**
- Modify: `plugins/LiveReservation/src/LiveReservationPlugin.php`
- Add: `plugins/LiveReservation/src/LiveReservationRemoteIndexLoader.php`
- Test: `tools/tmp-live-reservation-remote-index-check.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing `LiveReservation` remote-index check**

Create a temporary script asserting:

- `LiveReservation` now reads `reservation_lottery_infos.json`
- local `vmids` remain supported
- deduplication is based on `uid / up_mid`

- [ ] **Step 2: Run the check to verify it fails**

Run: `rtk php tools/tmp-live-reservation-remote-index-check.php`
Expected: failure because the plugin still only uses local `vmids`.

- [ ] **Step 3: Implement the mixed-source reservation flow**

Adapt `LiveReservation` to:

- load `reservation_lottery_infos.json`
- keep local `vmids`
- merge and dedupe by `uid / up_mid`
- preserve plugin-side API confirmation and reservation flow

- [ ] **Step 4: Re-run the check to verify it passes**

Run: `rtk php tools/tmp-live-reservation-remote-index-check.php`
Expected: PASS.

- [ ] **Step 5: Remove the temporary check and commit**

```bash
git add plugins/LiveReservation
git commit -m "refactor: combine reservation remote index with local vmids"
```

Before commit:

- delete `tools/tmp-live-reservation-remote-index-check.php`
- ensure no temporary script remains staged

### Task 9: Final Verification and Integration Commit

**Files:**
- Verify: `tools/activity_index/`
- Verify: `.github/workflows/activity-index-refresh.yml`
- Verify: `plugins/ActivityLottery/src/ActivityLotteryPlugin.php`
- Verify: `plugins/Lottery/src/`
- Verify: `plugins/LiveReservation/src/`
- Verify: `resources/*.json`

- [ ] **Step 1: Run Python test suite**

Run:

```bash
python -m pytest tools/activity_index/tests -q
```

Expected: all Python collector/parser/workflow tests pass.

- [ ] **Step 2: Run PHP syntax verification**

Run:

```bash
rtk php -l plugins/ActivityLottery/src/ActivityLotteryPlugin.php
rtk php -l plugins/Lottery/src/LotteryPlugin.php
rtk php -l plugins/LiveReservation/src/LiveReservationPlugin.php
```

Expected: syntax checks pass.

- [ ] **Step 3: Run app entry verification**

Run: `rtk php app.php --help`
Expected: help output still succeeds.

- [ ] **Step 4: Inspect repository state**

Run: `git status --short`
Expected:

- only intended production files modified
- no temporary verification scripts remain

- [ ] **Step 5: Create the final integration commit**

```bash
git add tools/activity_index .github/workflows/activity-index-refresh.yml plugins/ActivityLottery plugins/Lottery plugins/LiveReservation resources README.md docs
git commit -m "feat: add unified remote activity index pipeline"
```
