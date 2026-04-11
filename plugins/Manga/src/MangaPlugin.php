<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Manga;

use Bhp\Api\Manga\ApiManga;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\NoLoginException;

class MangaPlugin extends BasePlugin implements PluginTaskInterface
{
    private ?ApiManga $mangaApi = null;
    private ?AuthFailureClassifier $authFailureClassifier = null;
    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('manga')) {
            return TaskResult::keepSchedule();
        }

        $success = $this->shareTask() && $this->signInTask();

        return $success ? TaskResult::nextDayAt(10, 0, 0, 1, 60) : TaskResult::after(3600);
    }

    protected function signInTask(): bool
    {
        $response = $this->mangaApi()->ClockIn();
        $this->assertNotAuthFailure($response, '漫画: 签到时账号未登录');

        switch ($response['code']) {
            case 0:
                $this->notice('漫画: 签到成功');
                break;
            case 1:
            case 'invalid_argument':
                $this->notice('漫画: 今日已经签到过了哦~');
                break;
            default:
                $this->warning("漫画: 签到失败 {$response['code']} -> {$response['msg']}");

                return false;
        }
        $this->signInInfo();

        return true;
    }

    protected function shareTask(): bool
    {
        $response = $this->mangaApi()->ShareComic();
        $this->assertNotAuthFailure($response, '漫画: 分享时账号未登录');

        switch ($response['code']) {
            case 0:
                if ($response['msg'] === '今日已分享') {
                    $this->notice('漫画: 今日已经分享过了哦~');
                } else {
                    $this->notice("漫画: 分享成功，经验值+{$response['data']['point']}");
                }
                break;
            case 'unauthenticated':
                throw new NoLoginException($response['message']);
            default:
                $this->warning("漫画: 分享失败 {$response['code']} -> {$response['msg']}");

                return false;
        }

        return true;
    }

    protected function signInInfo(): void
    {
        $response = $this->mangaApi()->GetClockInInfo();
        $this->assertNotAuthFailure($response, '漫画: 获取签到信息时账号未登录');
        if ($response['code']) {
            $this->warning("漫画: 获取签到信息失败 {$response['code']} -> {$response['msg']}");
        } else {
            $this->notice("漫画: 已连续签到 {$response['data']['day_count']} 天，继续加油哦~");
        }
    }

    private function mangaApi(): ApiManga
    {
        return $this->mangaApi ??= new ApiManga($this->appContext()->request());
    }

    /**
     * @throws NoLoginException
     */
    private function assertNotAuthFailure(array $response, string $fallbackMessage): void
    {
        $this->authFailureClassifier ??= new AuthFailureClassifier();
        $this->authFailureClassifier->assertNotAuthFailure($response, $fallbackMessage);
    }
}
