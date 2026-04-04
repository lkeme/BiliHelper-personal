<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\BasePluginRW;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Remote\RemoteResourceResolver;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Resource\Resource;

class CheckUpdate extends BasePluginRW implements PluginTaskInterface
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'CheckUpdate', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '检查版本更新', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1001, // 插件优先级
        'cycle' => '24(小时)', // 运行周期
        'requires_auth' => false, // 调试模式下无需附带 Login
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        Cache::initCache();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('check_update')) {
            return TaskResult::keepSchedule();
        }

        return $this->_checkUpdate()
            ? TaskResult::after(24 * 60 * 60)
            : TaskResult::after(3 * 60 * 60);
    }

    /**
     * @return bool
     */
    protected function _checkUpdate(): bool
    {
        //
        Log::info('开始检查项目更新');
        // resource object
        $offline = $this->fetchOfflineVersion();
        $offline_version = $offline->get('version');
        //
        Log::info('拉取线上最新配置');
        // object
        $online = $this->fetchOnlineVersion();
        // 网络错误
        if ((int)($online->code ?? 0) !== 200) {
            Log::warning('检查更新: 拉取线上失败，网络错误！');
            return false;
        }
        $online_version = (string)($online->version ?? '');
        if ($online_version === '') {
            Log::warning('检查更新: 拉取线上失败，远端版本信息缺失！');
            return false;
        }
        // 比较版本
        if ($this->compareVersion($offline_version, $online_version)) {
            //
            $time = (string)($online->update_time ?? '');
            $desc = (string)($online->update_description ?? '');
            $info = "请注意版本变动更新哦~\n\n版本号: $online_version\n\n更新日志: $desc\n\n更新时间: $time\n\n";
            Log::notice($info);
            Notice::push('update', $info);
        } else {
            Log::notice("当前程序版本($offline_version)已是最新");
        }
        return true;
    }

    /**
     * 拉取本地版本
     * @return Resource
     */
    protected function fetchOfflineVersion(): Resource
    {
        $this->loadResource('version.json', 'json');
        return $this->resource;
    }

    /**
     * 拉取线上版本
     * @return object
     */
    protected function fetchOnlineVersion(): object
    {
        $resolver = new RemoteResourceResolver();
        $branch = $resolver->branch();
        Log::info("检查更新: 使用远程资源分支 {$branch}");
        $last = ['code' => -500, 'message' => '检查更新: 未获取到远程版本信息', 'data' => []];

        foreach ($resolver->resourceRawUrls('version.json') as $index => $url) {
            $host = trim((string)(parse_url($url, PHP_URL_HOST) ?? 'unknown'));
            $host = str_replace(['-', '.'], '_', $host);
            $label = sprintf('check_update.remote.version.%d.%s', $index + 1, $host);
            $response = \Bhp\Api\Support\ApiJson::get('other', $url, [], [], $label);
            if ((int)($response['code'] ?? 0) === 200 && trim((string)($response['version'] ?? '')) !== '') {
                return (object)$response;
            }

            $last = $response;
        }

        return (object)$last;
    }

    /**
     * 比较版本号
     * @param string $off
     * @param string $on
     * @return bool
     */
    protected static function compareVersion(string $off, string $on): bool
    {
        // true 有更新  false 无更新
        return !($off == $on);
    }

    /**
     * 重写系统路径
     * @param string $filename
     * @return string
     */
    protected function getFilePath(string $filename): string
    {
        return str_replace("\\", "/", APP_RESOURCES_PATH . $filename);
    }
}
