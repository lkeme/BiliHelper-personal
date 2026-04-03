<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Page;

final class EraPageParser
{
    public function __construct(
        private readonly EraTaskCapabilityResolver $capabilityResolver = new EraTaskCapabilityResolver(),
    ) {
    }

    public function parse(string $html): ?EraPageSnapshot
    {
        $initialState = $this->extractAssignedJson($html, 'window.__initialState');
        if ($initialState === null) {
            return null;
        }

        $pageInfo = $this->extractAssignedJson($html, 'window.__BILIACT_PAGEINFO__') ?? [];

        $activityId = trim((string)(
            $this->extractNested($initialState, ['EraLottery', 'config', 'activity_id'])
            ?? $this->extractNested($initialState, ['EraLotteryPc', 'config', 'activity_id'])
            ?? ($pageInfo['activity_id'] ?? '')
        ));
        $lotteryId = trim((string)(
            $this->extractNested($initialState, ['EraLottery', 'config', 'lottery_id'])
            ?? $this->extractNested($initialState, ['EraLotteryPc', 'config', 'lottery_id'])
            ?? ''
        ));
        $startTime = (int)(
            $this->extractNested($initialState, ['BaseInfo', 'start_time'])
            ?? $this->extractNested($initialState, ['BaseInfo', 'startTime'])
            ?? ($pageInfo['start_time'] ?? 0)
        );
        $endTime = (int)(
            $this->extractNested($initialState, ['BaseInfo', 'end_time'])
            ?? $this->extractNested($initialState, ['BaseInfo', 'endTime'])
            ?? ($pageInfo['end_time'] ?? 0)
        );

        return EraPageSnapshot::fromArray([
            'title' => trim((string)($pageInfo['title'] ?? $this->extractNested($initialState, ['BaseInfo', 'title']) ?? '')),
            'page_id' => trim((string)($pageInfo['page_id'] ?? '')),
            'activity_id' => $activityId,
            'lottery_id' => $lotteryId,
            'start_time' => max(0, $startTime),
            'end_time' => max(0, $endTime),
            'tasks' => $this->extractTasks($initialState),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTasks(array $state): array
    {
        $tasks = [];
        foreach (['EraTasklist', 'EraTasklistPc'] as $componentKey) {
            foreach (($state[$componentKey] ?? []) as $component) {
                if (!is_array($component)) {
                    continue;
                }

                foreach (($component['tasklist'] ?? []) as $task) {
                    if (!is_array($task)) {
                        continue;
                    }

                    $taskRow = [
                        'task_id' => trim((string)($task['taskId'] ?? $task['task_id'] ?? '')),
                        'task_name' => trim((string)($task['taskName'] ?? $task['task_name'] ?? '')),
                        'task_status' => (int)($task['taskStatus'] ?? $task['task_status'] ?? 0),
                        'task_award_type' => (int)($task['taskAwardType'] ?? $task['task_award_type'] ?? 0),
                        'topic_id' => trim((string)($task['topicID'] ?? $task['topic_id'] ?? '')),
                        'btn_behavior' => is_array($task['btnBehavior'] ?? null) ? $task['btnBehavior'] : [],
                    ];
                    $taskRow['capability'] = $this->capabilityResolver->resolve($taskRow);
                    $tasks[] = $taskRow;
                }
            }
        }

        return $tasks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractAssignedJson(string $html, string $assignment): ?array
    {
        $position = strpos($html, $assignment);
        if ($position === false) {
            return null;
        }

        $start = strpos($html, '{', $position);
        if ($start === false) {
            return null;
        }

        $json = $this->extractJsonObject($html, $start);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function extractJsonObject(string $source, int $start): ?string
    {
        $length = strlen($source);
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char !== '}') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }

        return null;
    }

    private function extractNested(mixed $value, array $segments, int $offset = 0): mixed
    {
        if ($offset >= count($segments)) {
            return $value;
        }
        if (!is_array($value)) {
            return null;
        }

        $segment = $segments[$offset];
        if (array_key_exists($segment, $value)) {
            return $this->extractNested($value[$segment], $segments, $offset + 1);
        }

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nested = $this->extractNested($item, $segments, $offset);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }
}

