<?php declare(strict_types=1);

namespace Bhp\Api\XLive\DataInterface\V1\X25Kn;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;

final class ApiTrace extends AbstractApiClient
{
    /**
     * 初始化 ApiTrace
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function enter(
        int $roomId,
        int $ruid,
        int $parentAreaId,
        int $areaId,
        string $buvid,
        string $uuid,
        string $userAgent,
    ): array {
        $query = WbiSign::encryption([
            'id' => self::compactJson([$parentAreaId, $areaId, 0, $roomId]),
            'device' => self::compactJson([$buvid, $uuid]),
            'ruid' => $ruid,
            'ts' => (int)round(microtime(true) * 1000),
            'is_patch' => 0,
            'heart_beat' => '[]',
            'ua' => $userAgent,
            'web_location' => '444.8',
            'csrf' => $this->request()->csrfValue(),
        ]);

        return $this->decodePost('pc', 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/E?' . http_build_query($query), [], [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/blanc/{$roomId}?liteVersion=true",
        ], 'xlive.trace.enter');
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public function heartbeat(array $session, string $userAgent, ?int $durationSeconds = null): array
    {
        $roomId = (int)($session['room_id'] ?? 0);
        $seqId = (int)($session['seq_id'] ?? 0) + 1;
        $duration = max(1, (int)($durationSeconds ?? $session['heartbeat_interval'] ?? 60));
        $ts = (int)round(microtime(true) * 1000);
        $query = WbiSign::encryption([
            's' => self::buildSignature(
                (int)($session['parent_area_id'] ?? 0),
                (int)($session['area_id'] ?? 0),
                $seqId,
                $roomId,
                (int)($session['ets'] ?? 0),
                $duration,
                $ts,
                (string)($session['secret_key'] ?? ''),
                self::normalizeRules($session['secret_rule'] ?? []),
                (string)($session['live_buvid'] ?? ''),
                (string)($session['live_uuid'] ?? ''),
            ),
            'id' => self::compactJson([
                (int)($session['parent_area_id'] ?? 0),
                (int)($session['area_id'] ?? 0),
                $seqId,
                $roomId,
            ]),
            'device' => self::compactJson([
                (string)($session['live_buvid'] ?? ''),
                (string)($session['live_uuid'] ?? ''),
            ]),
            'ruid' => (int)($session['ruid'] ?? 0),
            'ets' => (int)($session['ets'] ?? 0),
            'benchmark' => (string)($session['secret_key'] ?? ''),
            'time' => $duration,
            'ts' => $ts,
            'trackid' => -99998,
            'ua' => $userAgent,
            'web_location' => '444.8',
            'csrf' => $this->request()->csrfValue(),
        ]);

        return $this->decodePost('pc', 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/X?' . http_build_query($query), [], [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/blanc/{$roomId}?liteVersion=true",
        ], 'xlive.trace.heartbeat');
    }

    /**
     * @param list<int> $rules
     */
    private static function buildSignature(
        int $parentAreaId,
        int $areaId,
        int $seqId,
        int $roomId,
        int $ets,
        int $duration,
        int $ts,
        string $secretKey,
        array $rules,
        string $buvid,
        string $uuid,
    ): string {
        $payload = self::compactJson([
            'platform' => 'web',
            'parent_id' => $parentAreaId,
            'area_id' => $areaId,
            'seq_id' => $seqId,
            'room_id' => $roomId,
            'buvid' => $buvid,
            'uuid' => $uuid,
            'ets' => $ets,
            'time' => $duration,
            'ts' => $ts,
        ]);

        $current = $payload;
        foreach ($rules as $rule) {
            $current = hash_hmac(self::algoByRule($rule), $current, $secretKey);
        }

        return $current;
    }

    /**
     * @param mixed $rules
     * @return list<int>
     */
    private static function normalizeRules(mixed $rules): array
    {
        if (!is_array($rules)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $rule): int => (int)$rule, $rules));
    }

    /**
     * 处理algoByRule
     * @param int $rule
     * @return string
     */
    private static function algoByRule(int $rule): string
    {
        return match ($rule) {
            0 => 'md5',
            1 => 'sha1',
            2 => 'sha256',
            3 => 'sha224',
            4 => 'sha512',
            5 => 'sha384',
            default => 'sha256',
        };
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private static function compactJson(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
