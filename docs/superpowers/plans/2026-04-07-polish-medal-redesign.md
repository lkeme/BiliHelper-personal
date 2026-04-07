# PolishMedal Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `PolishMedal` into a windowed round-based plugin that only deletes cancelled medals and lights live unlit medals via single like requests.

**Architecture:** Replace the current barrage-driven monolith with three focused pieces: medal API wrappers, round state storage, and a scheduler-driven plugin flow. The plugin will hold only round-scoped queues in cache, run only between `08:00:00` and next-day `02:00:00`, and always execute one business action per scheduler tick.

**Tech Stack:** PHP 8.5, existing `Request`/`Cache`/`TaskResult` infrastructure, Bilibili live medal APIs, temporary local validation scripts under `tools/` that must be deleted before commit.

---

### Task 1: Probe the Medal List Source and Add the Final Medal API Wrapper

**Files:**
- Create: `src/Api/XLive/FansMedal/V1/ApiMedalManage.php`
- Test: `tools/tmp-polish-medal-source-probe.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing temporary probe script**

Create `tools/tmp-polish-medal-source-probe.php` that:

- bootstraps `vendor/autoload.php`
- instantiates the new `ApiMedalManage`
- asks it for page 1 with an aggressive page size candidate
- asserts the normalized item shape exposes the fields the plugin needs:
  - `medal_id`
  - `room_id`
  - `anchor_name`
  - `living_status`
  - `is_lighted`
- prints which underlying endpoint wins on field completeness and request count

The first version should fail because `ApiMedalManage` does not exist yet.

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$api = new \Bhp\Api\XLive\FansMedal\V1\ApiMedalManage($request);
$page = $api->listPage(1, 100);
assert(isset($page['items'][0]['living_status']));
```

- [ ] **Step 2: Run the temporary probe to verify it fails**

Run: `rtk php tools/tmp-polish-medal-source-probe.php`  
Expected: fatal error or runtime failure because `ApiMedalManage` is missing.

- [ ] **Step 3: Implement the final medal list wrapper**

Create `src/Api/XLive/FansMedal/V1/ApiMedalManage.php` with:

- one final chosen list endpoint only
- one normalized `listPage(int $page, int $pageSize): array` method
- one `deleteMedals(array $medalIds): array` method wrapping `/fans_medal/v5/live_fans_medal/deleteMedals`
- response decoding and transport error handling matching existing API classes

Important:

- probe both candidate list endpoints during implementation
- choose one winner
- delete the loser path before commit
- do not keep a runtime fallback branch

- [ ] **Step 4: Re-run the probe to verify it passes**

Run: `rtk php tools/tmp-polish-medal-source-probe.php`  
Expected:

- successful output identifying the chosen source
- required normalized fields present
- no fallback branch left undecided

- [ ] **Step 5: Remove the temporary probe script and commit**

Run:

```bash
rtk git add src/Api/XLive/FansMedal/V1/ApiMedalManage.php
rtk git commit -m "feat: add medal management api wrapper"
```

Before commit:

- delete `tools/tmp-polish-medal-source-probe.php`
- verify it is not staged

### Task 2: Add the Single-Request Like API for Medal Lighting

**Files:**
- Create: `src/Api/XLive/AppUcenter/V1/ApiLikeInfoV3.php`
- Test: `tools/tmp-polish-medal-like-api.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing temporary API script**

Create `tools/tmp-polish-medal-like-api.php` that expects a new like API wrapper with this shape:

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$api = new \Bhp\Api\XLive\AppUcenter\V1\ApiLikeInfoV3($request);
$result = $api->likeReportV3(123, 456, 33);
var_export(is_array($result));
```

The first run should fail because the wrapper does not exist.

- [ ] **Step 2: Run the temporary script to verify it fails**

Run: `rtk php tools/tmp-polish-medal-like-api.php`  
Expected: missing class or missing method failure.

- [ ] **Step 3: Implement the like API wrapper**

Create `src/Api/XLive/AppUcenter/V1/ApiLikeInfoV3.php` with:

- `likeReportV3(int $roomId, int $anchorId, int $clickTime): array`
- WBI-signed query parameters
- CSRF and current uid injection from `Request`
- transport failure handling consistent with existing API classes

The endpoint should match BLTH semantics: one request, randomized `click_time` set by the caller.

- [ ] **Step 4: Re-run the temporary script to verify it passes**

Run: `rtk php tools/tmp-polish-medal-like-api.php`  
Expected: script runs without class/method failures and returns decoded array output.

- [ ] **Step 5: Remove the temporary script and commit**

Run:

```bash
rtk git add src/Api/XLive/AppUcenter/V1/ApiLikeInfoV3.php
rtk git commit -m "feat: add medal like report api"
```

Before commit:

- delete `tools/tmp-polish-medal-like-api.php`
- verify it is not staged

### Task 3: Introduce Round State Storage for PolishMedal

**Files:**
- Create: `plugins/PolishMedal/src/PolishMedalRuntimeState.php`
- Create: `plugins/PolishMedal/src/PolishMedalStateStore.php`
- Test: `tools/tmp-polish-medal-state.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing temporary state script**

Create `tools/tmp-polish-medal-state.php` that expects:

- a store that can load/save one plugin state blob
- a runtime state object that normalizes:
  - `round_refreshed_at`
  - `round_delete_queue`
  - `round_light_queue`
  - `round_stats`

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$state = new \Bhp\Plugin\Builtin\PolishMedal\PolishMedalRuntimeState([]);
var_export($state->roundDeleteQueue());
```

- [ ] **Step 2: Run the temporary script to verify it fails**

Run: `rtk php tools/tmp-polish-medal-state.php`  
Expected: missing class failure.

- [ ] **Step 3: Implement runtime state and store**

Create:

- `plugins/PolishMedal/src/PolishMedalRuntimeState.php`
- `plugins/PolishMedal/src/PolishMedalStateStore.php`

Behavior:

- expose a normalized state shape
- keep only round-scoped queues and stats
- provide helpers to pop one delete item, pop one light item, clear the round, and snapshot queue counts

- [ ] **Step 4: Re-run the temporary script to verify it passes**

Run: `rtk php tools/tmp-polish-medal-state.php`  
Expected: normalized empty queues and default stats print correctly.

- [ ] **Step 5: Remove the temporary script and commit**

Run:

```bash
rtk git add plugins/PolishMedal/src/PolishMedalRuntimeState.php plugins/PolishMedal/src/PolishMedalStateStore.php
rtk git commit -m "feat: add PolishMedal round state storage"
```

Before commit:

- delete `tools/tmp-polish-medal-state.php`
- verify it is not staged

### Task 4: Rewrite PolishMedal Plugin Around Windowed Round Scheduling

**Files:**
- Modify: `plugins/PolishMedal/src/PolishMedalPlugin.php`
- Test: `tools/tmp-polish-medal-plugin-flow.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing plugin-flow script**

Create `tools/tmp-polish-medal-plugin-flow.php` that exercises the new intended behavior with stubbed inputs:

- outside `08:00:00` to next-day `02:00:00`: round is cleared and next run points to the next `08:00:00`
- inside the window: plugin refreshes one round
- delete queue wins over light queue
- one run consumes exactly one action
- when no actions exist, next run is `10-20` minutes later

The first run should fail because the plugin still contains the old barrage flow.

- [ ] **Step 2: Run the temporary script to verify it fails for the right reason**

Run: `rtk php tools/tmp-polish-medal-plugin-flow.php`  
Expected: assertion failure showing the plugin still uses old queue fields or old barrage semantics.

- [ ] **Step 3: Replace the old plugin logic with the new flow**

In `plugins/PolishMedal/src/PolishMedalPlugin.php`:

- delete old fields and helpers:
  - barrage APIs
  - reply words
  - invalid medal cache
  - blacklist
  - `everyday`
  - grey-medal color filtering
  - old lock fields
- inject and use:
  - `ApiMedalManage`
  - `ApiLikeInfoV3`
  - `PolishMedalStateStore`
  - `PolishMedalRuntimeState`
- add explicit window helpers for:
  - `isWithinRunWindow()`
  - `secondsUntilNextWindowStart()`
  - `shouldDiscardRoundAtTwoAm()`
- build one-round refresh logic that:
  - fetches medal pages
  - keeps only `anchor_name == '账号已注销'`
  - keeps only `living_status == 1 && is_lighted == 0`
  - caps live queue to 30
- process exactly one action per run:
  - delete first when enabled
  - otherwise light one medal with random `click_time` from `30` to `35`
- schedule:
  - `30-60` seconds between actions
  - `10-20` minutes when refresh yields nothing
  - next `08:00:00` when outside the run window

- [ ] **Step 4: Re-run the temporary flow script to verify it passes**

Run: `rtk php tools/tmp-polish-medal-plugin-flow.php`  
Expected:

- one-action-per-run behavior
- delete priority over light
- round discard after `02:00:00`
- direct wait to next `08:00:00` outside the window

- [ ] **Step 5: Remove the temporary script and commit**

Run:

```bash
rtk git add plugins/PolishMedal/src/PolishMedalPlugin.php
rtk git commit -m "refactor: rebuild PolishMedal scheduling flow"
```

Before commit:

- delete `tools/tmp-polish-medal-plugin-flow.php`
- verify it is not staged

### Task 5: Shrink Config and Update User-Facing Docs

**Files:**
- Modify: `profile/example/config/user.ini`
- Modify: `README.md`
- Test: `tools/tmp-polish-medal-config-check.php` (temporary, do not commit)

- [ ] **Step 1: Write the failing temporary config check**

Create `tools/tmp-polish-medal-config-check.php` that loads the example config as text and asserts:

- `[polish_medal]` still exists
- `enable` exists
- `cleanup_invalid_medal` exists
- `everyday` is absent
- `reply_words` is absent

```php
<?php
declare(strict_types=1);

$config = file_get_contents(__DIR__ . '/../profile/example/config/user.ini');
assert(!str_contains($config, 'everyday ='));
assert(!str_contains($config, 'reply_words ='));
```

- [ ] **Step 2: Run the temporary check to verify it fails**

Run: `rtk php tools/tmp-polish-medal-config-check.php`  
Expected: assertion failure because the old config keys still exist.

- [ ] **Step 3: Update config and docs**

In `profile/example/config/user.ini`:

- keep only:

```ini
[polish_medal]
enable = false
cleanup_invalid_medal = false
```

In `README.md`:

- update the plugin description so it no longer suggests grey-medal barrage lighting
- reflect live-like lighting semantics if the README has a dedicated description line

- [ ] **Step 4: Re-run the temporary check to verify it passes**

Run: `rtk php tools/tmp-polish-medal-config-check.php`  
Expected: config assertions all pass.

- [ ] **Step 5: Remove the temporary script and commit**

Run:

```bash
rtk git add profile/example/config/user.ini README.md
rtk git commit -m "chore: streamline PolishMedal config and docs"
```

Before commit:

- delete `tools/tmp-polish-medal-config-check.php`
- verify it is not staged

### Task 6: Final Verification and Integration Commit

**Files:**
- Verify: `src/Api/XLive/FansMedal/V1/ApiMedalManage.php`
- Verify: `src/Api/XLive/AppUcenter/V1/ApiLikeInfoV3.php`
- Verify: `plugins/PolishMedal/src/PolishMedalPlugin.php`
- Verify: `plugins/PolishMedal/src/PolishMedalRuntimeState.php`
- Verify: `plugins/PolishMedal/src/PolishMedalStateStore.php`
- Verify: `profile/example/config/user.ini`

- [ ] **Step 1: Run syntax checks**

Run:

```bash
rtk php -l src/Api/XLive/FansMedal/V1/ApiMedalManage.php
rtk php -l src/Api/XLive/AppUcenter/V1/ApiLikeInfoV3.php
rtk php -l plugins/PolishMedal/src/PolishMedalPlugin.php
rtk php -l plugins/PolishMedal/src/PolishMedalRuntimeState.php
rtk php -l plugins/PolishMedal/src/PolishMedalStateStore.php
```

Expected: `No syntax errors detected` for all files.

- [ ] **Step 2: Run app entry verification**

Run: `rtk php app.php --help`  
Expected: command help prints successfully.

- [ ] **Step 3: Run one final temporary integrated smoke check**

Use one last temporary script if needed to prove:

- plugin can instantiate
- outside-window scheduling clears round state
- inside-window scheduling returns a bounded `TaskResult`

Delete the script immediately after the check. Do not commit it.

- [ ] **Step 4: Inspect git state**

Run: `rtk git status --short`  
Expected:

- only intended production files modified
- no temporary test scripts present

- [ ] **Step 5: Create the final integration commit**

Run:

```bash
rtk git add src/Api/XLive/FansMedal/V1/ApiMedalManage.php src/Api/XLive/AppUcenter/V1/ApiLikeInfoV3.php plugins/PolishMedal/src/PolishMedalPlugin.php plugins/PolishMedal/src/PolishMedalRuntimeState.php plugins/PolishMedal/src/PolishMedalStateStore.php profile/example/config/user.ini README.md
rtk git commit -m "refactor: rebuild PolishMedal with live-like scheduling"
```

