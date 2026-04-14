<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway;

use Bhp\Api\Dynamic\ApiTopic;
use Bhp\Api\Api\X\Player\ApiPlayer;
use Bhp\Automation\Watch\VideoWatchService;
use Bhp\Automation\Watch\VideoWatchSession;
use Bhp\Util\Exceptions\RequestException;

final class WatchVideoGateway
{
    private readonly ApiPlayer $apiPlayer;
    private readonly ApiTopic $apiTopic;
    /**
     * @var callable(array<string, mixed>): ?array<string, mixed>
     */
    private readonly mixed $archiveNormalizer;
    /**
     * @var callable(string, int): array<int, array<string, mixed>>
     */
    private readonly mixed $topicArchiveFetcher;
    /**
     * @var callable(array<string, mixed>, string): bool
     */
    private readonly mixed $startAction;
    /**
     * @var callable(array<string, mixed>, int, string): bool
     */
    private readonly mixed $finishAction;
    /**
     * @var callable(string, string): array<string, mixed>
     */
    private readonly mixed $pageListFetcher;

    /**
     * 初始化 WatchVideoGateway
     * @param ApiPlayer $apiPlayer
     * @param ApiTopic $apiTopic
     * @param callable $archiveNormalizer
     * @param callable $topicArchiveFetcher
     * @param callable $startAction
     * @param callable $finishAction
     * @param callable $pageListFetcher
     * @param VideoWatchService $watchService
     */
    public function __construct(
        ApiPlayer $apiPlayer,
        ApiTopic $apiTopic,
        ?callable $archiveNormalizer = null,
        ?callable $topicArchiveFetcher = null,
        ?callable $startAction = null,
        ?callable $finishAction = null,
        ?callable $pageListFetcher = null,
        ?VideoWatchService $watchService = null,
    ) {
        $watchService ??= throw new \LogicException('WatchVideoGateway requires an explicit VideoWatchService.');
        $this->apiPlayer = $apiPlayer;
        $this->apiTopic = $apiTopic;
        $this->pageListFetcher = $pageListFetcher ?? fn (string $aid, string $bvid): array => $this->apiPlayer->pageList($aid, $bvid);
        $this->archiveNormalizer = $archiveNormalizer ?? fn (array $archive): ?array => $this->defaultNormalizeArchive($archive);
        $this->topicArchiveFetcher = $topicArchiveFetcher ?? fn (string $topicId, int $limit = 20): array => $this->defaultFetchTopicArchives($topicId, $limit);
        $this->startAction = $startAction ?? function (array $archive, string $sessionId) use ($watchService): bool {
            $archive = $this->defaultNormalizeArchive($archive);
            if ($archive === null) {
                return false;
            }

            try {
                $watchService->start(
                    (string)($archive['aid'] ?? ''),
                    (string)($archive['cid'] ?? ''),
                    [
                        'duration' => (int)($archive['duration'] ?? 0),
                        'bvid' => (string)($archive['bvid'] ?? ''),
                        'session_id' => $sessionId,
                    ],
                );
            } catch (\RuntimeException $exception) {
                $retryable = $this->resolveWatchFailure($exception);
                if ($retryable instanceof RequestException) {
                    throw $retryable;
                }

                return false;
            }

            return true;
        };
        $this->finishAction = $finishAction ?? function (array $archive, int $watchedSeconds, string $sessionId) use ($watchService): bool {
            $archive = $this->defaultNormalizeArchive($archive);
            if ($archive === null) {
                return false;
            }

            $session = VideoWatchSession::start(
                (string)($archive['aid'] ?? ''),
                (string)($archive['cid'] ?? ''),
                [
                    'duration' => (int)($archive['duration'] ?? 0),
                    'bvid' => (string)($archive['bvid'] ?? ''),
                    'session_id' => $sessionId,
                ],
            );

            try {
                return $watchService->finish($session, $watchedSeconds);
            } catch (\RuntimeException $exception) {
                $retryable = $this->resolveWatchFailure($exception);
                if ($retryable instanceof RequestException) {
                    throw $retryable;
                }

                return false;
            }
        };
    }

    /**
     * @param array<string, mixed> $archive
     * @return array<string, mixed>|null
     */
    public function normalizeArchive(array $archive): ?array
    {
        $normalized = ($this->archiveNormalizer)($archive);
        if (!is_array($normalized) || $normalized === []) {
            return null;
        }

        $aid = trim((string)($normalized['aid'] ?? ''));
        $bvid = trim((string)($normalized['bvid'] ?? ''));
        if ($aid === '' && $bvid === '') {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTopicArchives(string $topicId, int $limit = 20): array
    {
        $archives = ($this->topicArchiveFetcher)($topicId, $limit);
        return is_array($archives) ? array_values(array_filter($archives, 'is_array')) : [];
    }

    /**
     * @param array<string, mixed> $archive
     */
    public function start(array $archive, string $sessionId): bool
    {
        return (bool)($this->startAction)($archive, $sessionId);
    }

    /**
     * @param array<string, mixed> $archive
     */
    public function finish(array $archive, int $watchedSeconds, string $sessionId): bool
    {
        return (bool)($this->finishAction)($archive, $watchedSeconds, $sessionId);
    }

    /**
     * @param array<string, mixed> $archive
     * @return array<string, mixed>|null
     */
    private function defaultNormalizeArchive(array $archive): ?array
    {
        $aid = trim((string)($archive['aid'] ?? ''));
        $bvid = trim((string)($archive['bvid'] ?? ''));
        if ($aid === '' && $bvid === '') {
            return null;
        }

        if ($aid === '' && $bvid !== '') {
            $aid = $this->aidFromBvid($bvid);
        }

        $cid = trim((string)($archive['cid'] ?? ''));
        $duration = (int)($archive['duration'] ?? 0);
        if ($aid !== '' && $cid !== '' && $duration > 0) {
            return [
                'aid' => $aid,
                'cid' => $cid,
                'duration' => $duration,
                'title' => trim((string)($archive['title'] ?? '')),
                'bvid' => $bvid,
            ];
        }

        $response = ($this->pageListFetcher)($aid, $bvid);
        if (($response['code'] ?? 0) !== 0) {
            throw new RequestException(sprintf(
                'ERA视频分页接口异常 aid=%s bvid=%s code=%s message=%s',
                $aid !== '' ? $aid : '-',
                $bvid !== '' ? $bvid : '-',
                (string)($response['code'] ?? ''),
                (string)($response['message'] ?? $response['msg'] ?? '')
            ));
        }

        if (!isset($response['data'][0]) || !is_array($response['data'][0])) {
            return null;
        }

        $page = $response['data'][0];
        $cid = trim((string)($page['cid'] ?? ''));
        $duration = (int)($page['duration'] ?? 0);
        if ($cid === '' || $duration <= 0) {
            return null;
        }

        return [
            'aid' => $aid,
            'cid' => $cid,
            'duration' => $duration,
            'title' => trim((string)($archive['title'] ?? $page['part'] ?? '')),
            'bvid' => $bvid,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultFetchTopicArchives(string $topicId, int $limit = 20): array
    {
        $response = $this->apiTopic->feed($topicId, 0, '', $limit);
        if (($response['code'] ?? -1) !== 0) {
            throw new RequestException(sprintf(
                'ERA话题稿件接口异常 topic=%s code=%s message=%s',
                $topicId !== '' ? $topicId : '-',
                (string)($response['code'] ?? ''),
                (string)($response['message'] ?? $response['msg'] ?? '')
            ));
        }

        $items = $response['data']['topic_card_list']['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $archives = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $dynamic = $item['dynamic_card_item'] ?? null;
            if (!is_array($dynamic) || !(bool)($dynamic['visible'] ?? false)) {
                continue;
            }

            $archive = $dynamic['modules']['module_dynamic']['major']['archive'] ?? null;
            if (!is_array($archive)) {
                continue;
            }

            $aid = trim((string)($archive['aid'] ?? ''));
            if ($aid === '') {
                continue;
            }

            $archives[] = [
                'aid' => $aid,
                'bvid' => trim((string)($archive['bvid'] ?? '')),
                'title' => trim((string)($archive['title'] ?? '')),
            ];

            if (count($archives) >= $limit) {
                break;
            }
        }

        return $archives;
    }

    /**
     * 处理aidFromBvid
     * @param string $bvid
     * @return string
     */
    private function aidFromBvid(string $bvid): string
    {
        $bvid = trim($bvid);
        if ($bvid === '' || strlen($bvid) < 12) {
            return '';
        }

        $alphabet = 'fZodR9XQDSUm21yCkLt3xa4bgh5e6ivqB8wpHnJE7jKNPVYcfruM9sTzG';
        $map = [];
        $length = strlen($alphabet);
        for ($index = 0; $index < $length; $index++) {
            $map[$alphabet[$index]] = $index;
        }

        $positions = [11, 10, 3, 8, 4, 6];
        $result = 0;
        foreach ($positions as $power => $position) {
            $char = $bvid[$position] ?? '';
            if ($char === '' || !isset($map[$char])) {
                return '';
            }

            $result += $map[$char] * (58 ** $power);
        }

        return (string)(($result - 8728348608) ^ 177451812);
    }

    /**
     * 解析观看失败
     * @param \RuntimeException $exception
     * @return ?RequestException
     */
    private function resolveWatchFailure(\RuntimeException $exception): ?RequestException
    {
        $message = trim($exception->getMessage());
        foreach (['视频观看初始化失败', '视频观看首拍心跳失败', '视频观看收尾失败'] as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return new RequestException($message);
            }
        }

        return null;
    }
}

