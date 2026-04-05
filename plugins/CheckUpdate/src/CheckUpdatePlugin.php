<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\CheckUpdate;

use Bhp\Api\Support\ApiJson;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Remote\RemoteResourceResolver;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Resource\Resource;

class CheckUpdatePlugin extends BasePlugin implements PluginTaskInterface
{
    /**
     * 插件信息
     * @var array<string, int|string|bool>
     */
    public ?array $info = [
        'hook' => 'CheckUpdate',
        'name' => 'CheckUpdate',
        'version' => '0.0.1',
        'desc' => '检查版本更新',
        'author' => 'Lkeme',
        'priority' => 1001,
        'cycle' => '24(小时)',
        'requires_auth' => false,
    ];

    public function __construct(Plugin $plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('check_update')) {
            return TaskResult::keepSchedule();
        }

        return $this->checkUpdate()
            ? TaskResult::after(24 * 60 * 60)
            : TaskResult::after(3 * 60 * 60);
    }

    protected function checkUpdate(): bool
    {
        $this->info('开始检查项目更新');

        $offline = $this->fetchOfflineVersion();
        $offlineVersion = $offline->get('version');

        $this->info('拉取线上最新配置');
        $online = $this->fetchOnlineVersion();
        if ((int)($online->code ?? 0) !== 200) {
            $this->warning('检查更新: 拉取线上失败，网络错误！');

            return false;
        }

        $onlineVersion = (string)($online->version ?? '');
        if ($onlineVersion === '') {
            $this->warning('检查更新: 拉取线上失败，远端版本信息缺失！');

            return false;
        }

        if ($this->compareVersion((string)$offlineVersion, $onlineVersion)) {
            $time = (string)($online->update_time ?? '');
            $desc = (string)($online->update_description ?? '');
            $info = "请注意版本变动更新哦~\n\n版本号: $onlineVersion\n\n更新日志: $desc\n\n更新时间: $time\n\n";
            $this->notice($info);
            $this->notify('update', $info);
        } else {
            $this->notice("当前程序版本($offlineVersion)已是最新");
        }

        return true;
    }

    protected function fetchOfflineVersion(): Resource
    {
        return (new Resource())->loadF($this->versionResourcePath(), 'json');
    }

    protected function versionResourcePath(): string
    {
        return rtrim(str_replace('\\', '/', $this->appContext()->appRoot()), '/') . '/resources/version.json';
    }

    protected function fetchOnlineVersion(): object
    {
        $resolver = new RemoteResourceResolver($this->appContext());
        $branch = $resolver->branch();
        $this->info("检查更新: 使用远程资源分支 {$branch}");
        $last = ['code' => -500, 'message' => '检查更新: 未获取到远程版本信息', 'data' => []];

        foreach ($resolver->resourceRawUrls('version.json') as $index => $url) {
            $host = trim((string)(parse_url($url, PHP_URL_HOST) ?? 'unknown'));
            $host = str_replace(['-', '.'], '_', $host);
            $label = sprintf('check_update.remote.version.%d.%s', $index + 1, $host);
            try {
                $raw = $this->appContext()->request()->getText('other', $url);
                $response = ApiJson::decode($raw, $label);
            } catch (\Throwable $throwable) {
                $response = [
                    'code' => -500,
                    'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                    'data' => [],
                ];
            }
            if ((int)($response['code'] ?? 0) === 200 && trim((string)($response['version'] ?? '')) !== '') {
                return (object)$response;
            }

            $last = $response;
        }

        return (object)$last;
    }

    protected static function compareVersion(string $off, string $on): bool
    {
        return $off !== $on;
    }
}
