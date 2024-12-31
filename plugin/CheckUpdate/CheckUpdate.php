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
use Bhp\Plugin\Plugin;
use Bhp\Request\Request;
use Bhp\TimeLock\TimeLock;
use Bhp\Util\GhProxy\GhProxy;
use Bhp\Util\Resource\Resource;

class CheckUpdate extends BasePluginRW
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
        'priority' => 1000, // 插件优先级
        'cycle' => '24(小时)', // 运行周期
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        //
        TimeLock::initTimeLock();
        //
        Cache::initCache();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('check_update')) return;
        //
        if ($this->_checkUpdate()) {
            TimeLock::setTimes(24 * 60 * 60);
        } else {
            TimeLock::setTimes(3 * 60 * 60);
        }
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
        //
        Log::info('拉取线上最新配置');
        // object
        $online = $this->fetchOnlineVersion();
        // 网络错误
        if ($online->code != 200) {
            Log::warning('检查更新: 拉取线上失败，网络错误！');
            return false;
        }
        // 比较版本
        if ($this->compareVersion($offline->get('version'), $online->version)) {
            // TODO 完善消息 支持markdown
            $time = $online->time;
            $version = $online->version;
            $des = $online->des;
            $info = "请注意版本变动更新哦~\n\n版本号: $version\n\n更新日志: $des\n\n更新时间: $time\n\n";
            Log::notice($info);
            Notice::push('update', $info);
        } else {
            Log::info('程序已是最新版本');
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
        $branch = getConf('app.branch');
        $url = $this->resource->get($branch . '_raw_url');
        $url = GhProxy::mirror($url);
        $payload = [];
        // 防止错误拉取
//        if (is_null($url)) {
//            return json_decode('{"code":404}', false);
//        }
        //
        return Request::getJson(false, 'other', $url, $payload);
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
