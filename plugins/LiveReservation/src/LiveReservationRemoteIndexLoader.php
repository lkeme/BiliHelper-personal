<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveReservation;

use Bhp\Remote\RemoteResourceResolver;
use Bhp\Runtime\AppContext;

final class LiveReservationRemoteIndexLoader
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
        $path = rtrim(str_replace('\\', '/', $this->context->appRoot()), '/') . '/resources/reservation_lottery_infos.json';
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
        foreach ($resolver->resourceRawUrls('reservation_lottery_infos.json') as $url) {
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
                $upMid = trim((string)($record['up_mid'] ?? ''));
                if ($upMid === '') {
                    continue;
                }

                $key = 'up_mid:' . $upMid;
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'up_mid' => $upMid,
                        'sids' => [],
                    ];
                }

                $sid = trim((string)($record['sid'] ?? ''));
                if ($sid !== '' && !in_array($sid, $merged[$key]['sids'], true)) {
                    $merged[$key]['sids'][] = $sid;
                }
            }
        }

        return array_values($merged);
    }
}
