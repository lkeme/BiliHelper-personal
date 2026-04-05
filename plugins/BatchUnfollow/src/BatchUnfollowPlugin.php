<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\BatchUnfollow;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;

class BatchUnfollowPlugin extends BasePlugin
{
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiRelation $relationApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */
    public ?array $info = [
        'hook' => 'BatchUnfollow',
        'name' => 'BatchUnfollow',
        'version' => '0.0.1',
        'desc' => '批量取消关注',
        'author' => 'Lkeme',
        'priority' => 1116,
        'cycle' => 'manual',
        'mode' => 'script',
    ];

    /**
     * @var array<int, array{mid: mixed, uname: mixed}>
     */
    protected array $wait_unfollows = [];

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, false);
    }

    /**
     * @param array<string, mixed> $options
     * @param string[] $argv
     */
    public function execute(array $options = [], array $argv = []): void
    {
        if (!$this->enabled('batch_unfollow')) {
            $this->info('批量取关: 插件已关闭');

            return;
        }

        $this->fetchFollows();
        if (empty($this->wait_unfollows)) {
            $this->info('批量取关: 没有待处理关注');

            return;
        }

        while (!empty($this->wait_unfollows)) {
            $this->unfollow();
        }
    }

    protected function fetchFollows(): void
    {
        $follows = [];

        if ($this->config('batch_unfollow.tag') == 'all') {
            $response = $this->relationApi()->followings(1, 50);
            $this->authFailureClassifier->assertNotAuthFailure($response, '批量取关: 获取关注列表时账号未登录');
            if ($response['code'] != 0) {
                $this->warning("批量取关: 获取关注列表失败: {$response['code']} -> {$response['message']}");

                return;
            }
            foreach ($response['data']['list'] as $item) {
                $follows[] = ['mid' => $item['mid'], 'uname' => $item['uname']];
            }
        } else {
            $targetTag = $this->config('batch_unfollow.tag');

            $response = $this->relationApi()->tags();
            $this->authFailureClassifier->assertNotAuthFailure($response, '批量取关: 获取分组列表时账号未登录');
            if ($response['code'] != 0) {
                $this->warning("批量取关: 获取分组列表失败: {$response['code']} -> {$response['message']}");

                return;
            }

            $tagId = 0;
            foreach ($response['data'] as $item) {
                if ($item['name'] == $targetTag) {
                    $tagId = $item['tagid'];
                    break;
                }
            }
            if ($tagId == 0) {
                $this->warning("批量取关: 未找到目标分组: {$targetTag}");

                return;
            }

            $response = $this->relationApi()->tag($tagId, 1, 50);
            $this->authFailureClassifier->assertNotAuthFailure($response, '批量取关: 获取分组内列表时账号未登录');
            if ($response['code'] != 0) {
                $this->warning("批量取关: 获取分组内列表失败: {$response['code']} -> {$response['message']}");

                return;
            }

            foreach ($response['data']['list'] as $item) {
                $follows[] = ['mid' => $item['mid'], 'uname' => $item['uname']];
            }
        }

        $this->wait_unfollows = $follows;
        $this->info('批量取关: 获取关注列表成功 Count: ' . count($follows));
    }

    protected function unfollow(): void
    {
        $follow = array_shift($this->wait_unfollows);
        if (is_null($follow)) {
            $this->info('批量取关: 暂无关注列表');

            return;
        }

        $this->info("批量取关: 尝试取关用户: {$follow['uname']}({$follow['mid']})");

        $response = $this->relationApi()->modify((int)$follow['mid']);
        $this->authFailureClassifier->assertNotAuthFailure($response, '批量取关: 执行取关时账号未登录');
        if ($response['code'] != 0) {
            $this->warning("批量取关: 取关失败: {$response['code']} -> {$response['message']}");

            return;
        }

        $this->notice('批量取关: 取关成功');
    }

    private function relationApi(): ApiRelation
    {
        return $this->relationApi ??= new ApiRelation($this->appContext()->request());
    }
}
