# PolishMedal Redesign

**Goal:** Rebuild `PolishMedal` into a narrow, scheduler-friendly plugin that only deletes cancelled medals for logged-off anchors and lights live unlit medals via single like requests.

**Scope:** This redesign removes the old barrage-based flow, removes blacklist and manual-review concepts, shrinks the user config, and constrains execution to a daily time window from `08:00:00` to next-day `02:00:00`.

## Current Problems

The current implementation in [plugins/PolishMedal/src/PolishMedalPlugin.php](/D:/WorkSpace/PhpCode/BiliHelper-personal-ws/plugins/PolishMedal/src/PolishMedalPlugin.php):

- uses barrage sending to light medals, which leaves traces and carries avoidable moderation risk
- mixes multiple concepts: grey medals, manual reply words, blacklist, invalid-medal cache, and optional full-daily scan
- only marks invalid medals and does not perform the requested delete flow
- keeps state with ad-hoc locks that are hard to reason about when the plugin should throttle itself without blocking other plugins

## Final Business Rules

The redesigned plugin only recognizes two actionable medal categories:

1. **Logged-off anchors**
- Medal belongs to an anchor whose account is already cancelled
- When `cleanup_invalid_medal=true`, the medal should be deleted immediately
- When `cleanup_invalid_medal=false`, the medal is ignored

2. **Live and unlit medals**
- Medal belongs to a room whose `living_status == 1`
- Medal itself is not lighted yet
- It should be lighted by a single `likeReportV3` request

Everything else is ignored:

- offline medals
- already-lighted medals
- room-id edge cases
- manual blacklists
- manual review queues
- barrage fallback
- `everyday`
- reply-word customization
- long-lived protection caches

## Execution Window

The plugin may run only inside this window:

- start: `08:00:00`
- end: next-day `02:00:00`

Rules:

- from `02:00:00` to `07:59:59`, the plugin does not execute business actions
- if the current round still has pending work at `02:00:00`, the entire round is discarded
- after `08:00:00`, the plugin fetches fresh medal data and starts a new round
- no work is carried across the non-running window

## Round Model

The plugin uses a round-based snapshot model.

### Round start

When there is no active round queue, the plugin refreshes the medal list once and derives:

- delete queue: logged-off anchors
- light queue: live and unlit medals

The light queue is capped at **30** items per round.

### Round consumption

Each `runOnce()` may perform exactly **one** action:

- delete one medal, or
- light one medal

Delete work has higher priority than light work.

### Round completion

When both queues are empty, the round is complete. The plugin waits a randomized delay and then starts a new round by refreshing the list again.

## Scheduling Strategy

### Inside running window

- between two business actions: random `30-60` seconds
- after a round refresh that yields no actionable medals: random `10-20` minutes
- after a round is exhausted: random `30-60` seconds, then refresh into a new round

### Outside running window

- immediately clear any active round queues
- schedule directly to the next `08:00:00`

## API Strategy

### Medal list source

Implementation must compare the following two candidate sources and select exactly one final source:

- `xlive/app-ucenter/v1/fansMedal/panel`
- `fans_medal/v2/HighQps/received_medals`

Selection criteria:

- maximum supported `page_size`
- field completeness for:
  - `living_status`
  - lighted state
  - `medal_id`
  - `room_id`
  - anchor identity fields
- total request count under large medal counts, including up to `1000`

The final implementation must not keep a fallback dual-path logic. One source is chosen and used consistently.

### Light medal API

Lighting uses one single request with BLTH-aligned semantics:

- request: `likeReportV3`
- `click_time`: random `30-35`

This is a single request carrying the count, not multiple like requests.

### Delete medal API

Delete uses:

- `POST /fans_medal/v5/live_fans_medal/deleteMedals`

The user explicitly chose **direct delete without second confirmation** inside the plugin flow.

## State and Cache Design

The redesign intentionally keeps state minimal. The plugin should persist only round-scoped state that helps pacing across scheduler ticks.

Recommended state keys:

- `round_refreshed_at`
- `round_delete_queue`
- `round_light_queue`
- `round_stats`

State that must be removed from the old design:

- blacklist
- invalid-medal cache
- reply words
- grey-medal lock model
- daily full-scan semantics

## Logging

Logs should stay compact and operationally useful.

### Keep

- one round refresh summary:
  - total medal count
  - logged-off count
  - live-unlit count
  - round light queue count
- one delete result log per delete action
- one light result log per light action
- one window-skip / round-discard log when crossing out of the allowed window

### Remove

- per-item scan logs
- barrage logs
- blacklist logs
- invalid-medal marker logs

## Config Shape

The final user config block becomes:

```ini
[polish_medal]
enable = false
cleanup_invalid_medal = false
```

The following old config items must be removed:

- `everyday`
- `reply_words`

## File-Level Design

### Modify

- `plugins/PolishMedal/src/PolishMedalPlugin.php`
  - rebuild plugin control flow around windowed round scheduling
- `profile/example/config/user.ini`
  - shrink `polish_medal` config
- `README.md`
  - update plugin description if needed

### Add or extend API support

- likely new or expanded medal API wrapper under `src/Api/XLive/...` or `src/Api/...`
  - one method for final chosen medal list source
  - one method for delete
  - one method for like-report if not already present

### Optional internal split

If the existing plugin file becomes too dense, split internals into focused helpers:

- round state loader/saver
- medal list normalization
- scheduler/window policy
- delete executor
- light executor

This split is recommended only if it materially improves clarity during implementation.

## Non-Goals

These are explicitly out of scope:

- barrage fallback
- user-defined pacing knobs
- user-defined batch size
- user-defined `click_time`
- manual review queue
- blacklist persistence
- compatibility layer preserving old behavior

## Open Implementation Checks

These are not open product questions; they are implementation checks that must be answered during coding:

1. Which medal list API is the final source after measuring page-size and field completeness?
2. Which exact field combination is the most reliable way to identify “anchor logged off”?
3. Whether the delete API expects a scalar medal id or a list payload wrapper in the PHP request implementation
4. Whether the chosen list API already exposes enough data to avoid extra per-medal detail requests

