<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Remote\RemoteResourceResolver;
use Bhp\Runtime\AppContext;

final class LotteryRemoteIndexLoader
{
    public function __construct(
        private readonly AppContext $context,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function load(): array
    {
        return $this->normalizeRecords(
            $this->loadLocalRecords(),
            $this->loadRemoteRecords(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadLocalRecords(): array
    {
        $path = rtrim(str_replace('\\', '/', $this->context->appRoot()), '/') . '/resources/interactive_lottery_infos.json';
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        return $this->decodeRecords($raw);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRemoteRecords(): array
    {
        $resolver = new RemoteResourceResolver($this->context);
        foreach ($resolver->resourceRawUrls('interactive_lottery_infos.json') as $url) {
            try {
                $raw = $this->context->request()->getText('other', $url);
            } catch (\Throwable) {
                continue;
            }

            $records = $this->decodeRecords($raw);
            if ($records !== []) {
                return $records;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeRecords(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param array<int, array<string, mixed>> ...$sources
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRecords(array ...$sources): array
    {
        $merged = [];
        foreach ($sources as $records) {
            foreach ($records as $record) {
                $dynamicId = trim((string)($record['dynamic_id'] ?? ''));
                if ($dynamicId === '') {
                    continue;
                }

                $merged[$dynamicId] = [
                    'title' => trim((string)($record['title'] ?? '')),
                    'source_cv_id' => trim((string)($record['source_cv_id'] ?? '')),
                    'source_url' => trim((string)($record['source_url'] ?? '')),
                    'publish_time' => trim((string)($record['publish_time'] ?? '')),
                    'draw_time' => trim((string)($record['draw_time'] ?? '')),
                    'dynamic_id' => $dynamicId,
                    'dynamic_url' => trim((string)($record['dynamic_url'] ?? '')),
                    'reserve_rid' => trim((string)($record['reserve_rid'] ?? '')),
                    'up_mid' => trim((string)($record['up_mid'] ?? '')),
                    'requires_follow' => (bool)($record['requires_follow'] ?? false),
                    'requires_repost' => (bool)($record['requires_repost'] ?? false),
                    'requires_comment' => (bool)($record['requires_comment'] ?? false),
                    'prize_summary' => trim((string)($record['prize_summary'] ?? '')),
                    'update_time' => trim((string)($record['update_time'] ?? '')),
                ];
            }
        }

        return array_values($merged);
    }
}
