<?php declare(strict_types=1);

namespace Bhp\Activity\Era;

final class EraPagePayloadExtractor
{
    /**
     * @return array<string, mixed>|null
     */
    public function extractState(string $html): ?array
    {
        $state = $this->extractAssignedJson($html, 'window.__initialState') ?? [];
        $evaComponentState = $this->extractEvaComponentState($html);
        if ($state === [] && $evaComponentState === []) {
            return null;
        }

        return $this->mergeComponentState($state, $evaComponentState);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractPageInfo(string $html): array
    {
        return $this->extractAssignedJson($html, 'window.__BILIACT_PAGEINFO__') ?? [];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function extractEvaComponentState(string $html): array
    {
        $evaPageData = $this->extractAssignedJson($html, 'window.__BILIACT_EVAPAGEDATA__');
        if ($evaPageData === null) {
            return [];
        }

        $components = [];
        $this->collectEvaComponents($evaPageData, $components);

        return $components;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $components
     */
    private function collectEvaComponents(mixed $value, array &$components): void
    {
        if (!is_array($value)) {
            return;
        }

        $name = $value['name'] ?? null;
        $props = $value['props'] ?? null;
        if (is_string($name) && $name !== '' && is_array($props)) {
            $components[$name][] = $props;
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectEvaComponents($child, $components);
            }
        }
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, array<int, array<string, mixed>>> $componentState
     * @return array<string, mixed>
     */
    private function mergeComponentState(array $state, array $componentState): array
    {
        foreach ($componentState as $componentName => $items) {
            if ($items === []) {
                continue;
            }

            if (!array_key_exists($componentName, $state)) {
                $state[$componentName] = $items;
                continue;
            }

            if (!is_array($state[$componentName])) {
                continue;
            }

            if (array_is_list($state[$componentName])) {
                $state[$componentName] = array_values(array_merge($state[$componentName], $items));
                continue;
            }

            $state[$componentName] = array_values(array_merge([$state[$componentName]], $items));
        }

        return $state;
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

        for ($index = $start; $index < $length; $index++) {
            $char = $source[$index];

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
                return substr($source, $start, $index - $start + 1);
            }
        }

        return null;
    }
}
