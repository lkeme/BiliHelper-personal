<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Schedule;

use Bhp\Log\Log;
use Bhp\Util\DesignPattern\SingleTon;
use Bhp\Util\Os\File;

class Schedule extends SingleTon
{
    protected string $repository = '';

    /**
     * 初始化
     * @return void
     */
    public function init(): void
    {
        if (self::getInstance()->repository === '') {
            self::getInstance()->repository = PROFILE_TASK_PATH . getConf('login_account.username') . '.json';
            // 仓库不存在自动创建
            // 仓库不存在自动创建
            if (!file_exists(self::getInstance()->repository)) {
                $fh = fopen(self::getInstance()->repository, "w");
                fwrite($fh, "{}");
                fclose($fh);
                Log::info('任务排程文件不存在，初始化所有任务。');
            } else {
                Log::info('任务排程文件存在，继续执行所有任务。');
            }
        }
    }

    /**
     * 读
     * @return array
     */
    protected function reader(): array
    {
        $data = file_get_contents($this->repository);
        return json_decode($data, true) ?? [];
    }

    /**
     * 写
     * @param array $data
     * @return int|false
     */
    protected function writer(array $data): int|false
    {
        return file_put_contents($this->repository, json_encode($data));
    }

    /**
     * 写入
     * @param string $class
     * @param int $lock
     * @return void
     */
    public static function set(string $class, int $lock): void
    {
        $data = self::getInstance()->reader();
        $data[$class] = $lock;
        self::getInstance()->writer($data);
    }

    /**
     * 读取
     * @param string $class
     * @return int
     */
    public static function get(string $class): int
    {
        $data = self::getInstance()->reader();
        if (array_key_exists($class, $data)) {
            return $data[$class];
        }
        return 0;
    }

    /**
     * 复位
     */
    public static function restore(): void
    {
        Log::info('复位任务排程文件。');
        File::del(self::getInstance()->repository);
        self::getInstance()->repository = '';
        self::getInstance()->init();
    }


}
