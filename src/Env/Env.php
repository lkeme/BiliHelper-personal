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

namespace Bhp\Env;

use Bhp\Log\Log;
use Bhp\Profile\ProfileContext;
use Bhp\Util\Resource\BaseResource;
use Bhp\Util\AppTerminator;

class Env extends BaseResource
{
    public function __construct(
        private readonly ProfileContext $profileContext,
        private readonly Log $log,
        string $filename = 'version.json',
    )
    {
        set_time_limit(0);
        // header("Content-Type:text/html; charset=utf-8");
        // ini_set('date.timezone', 'Asia/Shanghai');
        date_default_timezone_set('Asia/Shanghai');
        ini_set('display_errors', 'on');
        error_reporting(E_ALL);
        //
        $this->loadResource($filename, 'json');
        //
        $this->app_name = $this->resource->get('project', 'BiliHelper-personal');
        $this->app_version = $this->resource->get('version', '0.0.0.000000');
        $overrideBranch = trim((string)getenv('BRANCH'));
        $this->app_branch = $overrideBranch !== '' ? $overrideBranch : $this->resource->get('branch', 'master');
        $this->app_source = $this->resource->get('source', 'https://github.com/lkeme/BiliHelper-personal');
        //
        $this->inspectConfigure()->inspectExtension();
    }

    /**
     * @var string
     */
    public string $app_name;

    /**
     * @var string
     */
    public string $app_version;

    /**
     * @var string
     */
    public string $app_branch;

    /**
     * @var string
     */
    public string $app_source;

    /**
     * 检查是否开启
     * @return $this
     */
    protected function inspectExtension(): Env
    {
        $default_extensions = ['openssl', 'json', 'zlib', 'mbstring', 'sqlite3'];
        foreach ($default_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $this->log->recordError("检查到项目依赖 $extension 扩展未加载。");
                $this->log->recordError("请在 php.ini中启用 $extension 扩展后重试。");
                $this->log->recordError("程序常见问题请移步 $this->app_source 文档部分查看。");
                AppTerminator::fail('');
            }
        }
        return $this;
    }

    /**
     * 检查php环境
     * @return $this
     */
    protected function inspectConfigure(): Env
    {
        $this->log->recordInfo("欢迎使用 项目: $this->app_name@$this->app_branch 版本: $this->app_version");
        $this->log->recordInfo("使用说明请移步 $this->app_source 查看");

        if (PHP_SAPI != 'cli') {
            AppTerminator::fail('Please run this script from command line .');
        }
        if (version_compare(PHP_VERSION, '8.4.0', '<')) {
            AppTerminator::fail('Please upgrade PHP version >= 8.4.0 .');
        }
        return $this;
    }

    /**
     * 重写获取路径
     * @param string $filename
     * @return string
     */
    protected function getFilePath(string $filename): string
    {
        return str_replace("\\", "/", $this->profileContext->resourcesPath() . $filename);
    }

    /**
     * Check: running in docker?
     * @return bool
     */
    public static function isDocker(): bool
    {
        // Win直接跳出
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return false;
        }
        if (!file_exists('/.dockerenv')) {
            return false;
        }
        // 检查/proc/1/cgroup内是否包含"docker"等字符串；
        if (!is_file('/proc/self/cgroup') || !preg_match('%^\d+:\w+:/(docker|actions_job)/' . preg_quote(gethostname(), '%') . '\w+%sm', file_get_contents('/proc/self/cgroup'))) {
            return false;
        }
//        $processStack = explode(PHP_EOL, shell_exec('cat /proc/self/cgroup | grep docker'));
//        $processStack = array_filter($processStack);
//        return count($processStack) > 0;
        return true;
    }
}
