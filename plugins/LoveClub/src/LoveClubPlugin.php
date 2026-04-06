<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LoveClub;

use Bhp\Api\LinkGroup\ApiLoveClub;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\NoLoginException;

class LoveClubPlugin extends BasePlugin implements PluginTaskInterface
{
    private ?ApiLoveClub $loveClubApi = null;
    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('love_club')) {
            return TaskResult::keepSchedule();
        }

        try {
            $groups = $this->getGroupList();
            foreach ($groups as $group) {
                $this->signInGroup($group);
            }
        } catch (NoLoginException $e) {
            $this->warning("友爱社: {$e->getMessage()}");

            return TaskResult::after(3600);
        }

        return TaskResult::nextAt(10);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getGroupList(): array
    {
        $response = $this->loveClubApi()->myGroups();

        switch ($response['code']) {
            case -101:
                throw new NoLoginException($response['message']);
            case 0:
                if (empty($response['data']['list'])) {
                    $this->notice('友爱社: 没有需要签到的应援团哦~');

                    return [];
                }

                return $response['data']['list'];
            default:
                $this->warning("友爱社: 获取应援团失败 {$response['code']} -> {$response['message']}");

                return [];
        }
    }

    /**
     * @param array<string, mixed> $group
     */
    protected function signInGroup(array $group): bool
    {
        $response = $this->loveClubApi()->signIn((int)$group['group_id'], (int)$group['owner_uid']);
        if ($response['code']) {
            if ($response['code'] == '710001') {
                $this->notice("友爱社: {$group['group_name']} 签到失败, 亲密度已达上限了哦~");
            } else {
                $this->warning("友爱社: {$group['group_name']} 签到失败, {$response['code']} -> {$response['message']}");
            }

            return false;
        }

        if ($response['data']['status']) {
            $this->notice("友爱社: {$group['group_name']} 签到失败, 今日已经签到过了哦~");
        } else {
            $this->notice("友爱社: {$group['group_name']} 签到成功, 亲密度+{$response['data']['add_num']}点");
        }

        return true;
    }

    private function loveClubApi(): ApiLoveClub
    {
        return $this->loveClubApi ??= new ApiLoveClub($this->appContext()->request());
    }
}
