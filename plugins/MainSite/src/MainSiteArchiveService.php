<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\MainSite;

use Bhp\Api\Api\X\Player\ApiPlayer;
use Bhp\Api\DynamicSvr\ApiDynamicSvr;
use Bhp\Api\Video\ApiVideo;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Log\Log;
use Bhp\Util\ArrayR\ArrayR;

class MainSiteArchiveService
{
    /**
     * 初始化 MainSiteArchiveService
     * @param Log $log
     * @param ApiVideo $apiVideo
     * @param ApiDynamicSvr $dynamicSvrApi
     * @param ApiPlayer $playerApi
     */
    public function __construct(
        private readonly Log $log,
        private readonly ApiVideo $apiVideo,
        private readonly ?ApiDynamicSvr $dynamicSvrApi = null,
        private readonly ?ApiPlayer $playerApi = null,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPreferredArchives(string $mode, int $num = 30): array
    {
        if ($mode === 'random') {
            return $this->topArchives($num);
        }

        return $this->followUpArchives($num);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topArchives(int $num, int $ps = 30): array
    {
        $response = $this->fetchDynamicRegion($ps);
        if (($response['code'] ?? 0) !== 0) {
            $code = $response['code'] ?? 'unknown';
            $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
            $this->log->recordWarning("主站任务: 获取首页推荐失败 {$code} -> {$message}");

            return $this->topFeedArchives($num);
        }

        $archives = $this->extractArchiveList($response['data']['archives'] ?? null);
        if ($archives === []) {
            return $this->topFeedArchives($num);
        }

        return ArrayR::toSlice($archives, $num);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topFeedArchives(int $num): array
    {
        $newArchives = [];
        $response = $this->fetchTopFeedRcmd();
        if (($response['code'] ?? 0) !== 0) {
            $code = $response['code'] ?? 'unknown';
            $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
            $this->log->recordWarning("主站任务: 获取首页推荐备选稿件失败 {$code} -> {$message}");

            return [];
        }

        $archives = ArrayR::toSlice($this->extractTopFeedItems($response['data']['item'] ?? null), $num);
        foreach ($archives as $archive) {
            $archive['aid'] = $archive['id'];
            unset($archive['id']);
            $newArchives[] = $archive;
        }

        return $newArchives;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function followUpArchives(int $num): array
    {
        $archives = [];
        $response = $this->fetchFollowUpDynamic();
        (new AuthFailureClassifier())->assertNotAuthFailure($response, '主站任务: 获取关注动态时账号未登录');
        if (($response['code'] ?? 0) !== 0) {
            $code = $response['code'] ?? 'unknown';
            $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
            $this->log->recordWarning("主站任务: 获取关注动态失败 {$code} -> {$message}");
        } else {
            $archives = $this->extractDynamicArchives($this->extractFollowUpItems($response['data'] ?? null), $num);
            $this->log->recordInfo('主站任务: 命中关注动态稿件 ' . count($archives));
        }

        if (($currentNum = count($archives)) < $num) {
            $fallback = $this->topArchives($num - $currentNum);
            if ($fallback !== []) {
                $this->log->recordWarning('主站任务: 关注动态稿件不足，将自动补全随机稿件。');
                $this->log->recordInfo('主站任务: 自动补全随机稿件 ' . count($fallback));
            }

            $archives = array_merge($archives, $fallback);
        }

        return $archives;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchArchiveInfo(string $aid): array
    {
        $response = $this->fetchPlayerPageList($aid);
        (new AuthFailureClassifier())->assertNotAuthFailure($response, "主站任务: {$aid} 获取稿件信息时账号未登录");
        if (($response['code'] ?? 0) === -404) {
            $code = $response['code'] ?? 'unknown';
            $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
            $this->log->recordWarning("主站任务: {$aid} 获取稿件信息失败 {$code} -> {$message}");

            return [];
        }

        $archiveInfo = $this->extractArchiveInfo($response['data'] ?? null);
        if ($archiveInfo === []) {
            return [];
        }

        $archiveInfo['aid'] = $aid;
        return $archiveInfo;
    }

    /**
     * @param array<int, array<string, mixed>> $archives
     * @return array<string, mixed>
     */
    public function pickLastArchive(array $archives): array
    {
        $archive = $archives === [] ? null : $archives[array_key_last($archives)];

        return is_array($archive) ? $archive : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchDynamicRegion(int $ps): array
    {
        return $this->apiVideo->dynamicRegion($ps);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchTopFeedRcmd(): array
    {
        return $this->apiVideo->topFeedRCMD();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchFollowUpDynamic(): array
    {
        return $this->dynamicSvrApi()->followUpDynamic();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchPlayerPageList(string $aid): array
    {
        return $this->playerApi()->pageList($aid);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    protected function extractDynamicArchives(array $items, int $limit): array
    {
        $archives = [];
        foreach ($items as $item) {
            if (!(bool)($item['visible'] ?? false)) {
                continue;
            }

            $archive = $item['modules']['module_dynamic']['major']['archive'] ?? null;
            if (!$this->isValidArchive($archive)) {
                continue;
            }

            $archives[] = $archive;
            if (count($archives) >= $limit) {
                break;
            }
        }

        return $archives;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractArchiveList(mixed $archives): array
    {
        if (!is_array($archives)) {
            return [];
        }

        return array_values(array_filter(
            $archives,
            fn (mixed $archive): bool => $this->isValidArchive($archive)
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractTopFeedItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            $items,
            static fn (mixed $archive): bool => is_array($archive) && isset($archive['id']) && (is_int($archive['id']) || is_string($archive['id']))
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractFollowUpItems(mixed $data): array
    {
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            return [];
        }

        return array_values(array_filter($data['items'], static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractArchiveInfo(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $archiveInfo = $data[0] ?? null;

        return is_array($archiveInfo) ? $archiveInfo : [];
    }

    /**
     * 判断ValidArchive是否满足条件
     * @param mixed $archive
     * @return bool
     */
    protected function isValidArchive(mixed $archive): bool
    {
        return is_array($archive)
            && isset($archive['aid'])
            && (is_int($archive['aid']) || is_string($archive['aid']));
    }

    /**
     * 处理dynamicSvrAPI
     * @return ApiDynamicSvr
     */
    private function dynamicSvrApi(): ApiDynamicSvr
    {
        if ($this->dynamicSvrApi instanceof ApiDynamicSvr) {
            return $this->dynamicSvrApi;
        }

        throw new \LogicException('MainSiteArchiveService requires an explicit ApiDynamicSvr.');
    }

    /**
     * 处理playerAPI
     * @return ApiPlayer
     */
    private function playerApi(): ApiPlayer
    {
        if ($this->playerApi instanceof ApiPlayer) {
            return $this->playerApi;
        }

        throw new \LogicException('MainSiteArchiveService requires an explicit ApiPlayer.');
    }
}
