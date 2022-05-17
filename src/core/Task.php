<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use BiliHelper\Tool\File;
use BiliHelper\Util\Singleton;

class Task
{
    use Singleton;

    private string $repository = '';

    /**
     * @use 初始化
     */
    protected function init(): void
    {
        // 赋值仓库地址
        if ($this->repository == '') {
            $this->repository = APP_TASK_PATH . 'tasks_' . getConf('username', 'login.account') . '.json';
            // 仓库不存在自动创建
            if (!file_exists($this->repository)) {
                $fh = fopen($this->repository, "w");
                fwrite($fh, "{}");
                fclose($fh);
                Log::info('任务排程文件不存在，初始化所有任务。');
            } else {
                Log::info('任务排程文件存在，继续执行所有任务。');
            }
        }
    }

    /**
     * @use 读
     * @return array
     */
    private function read(): array
    {
        $data = file_get_contents($this->repository);
        return json_decode($data, true);
    }

    /**
     * @use 写
     * @param array $data
     * @return bool
     */
    private function write(array $data): bool
    {
        return file_put_contents($this->repository, json_encode($data));
    }

    /**
     * @use 写入
     * @param string $class
     * @param int $lock
     * @return bool
     */
    public function _setLock(string $class, int $lock): bool
    {
        $data = $this->read();
        $data[$class] = $lock;
        return $this->write($data);
    }

    /**
     * @use 读取
     * @param string $class
     * @return int
     */
    public function _getLock(string $class): int
    {
        $data = $this->read();
        if (array_key_exists($class, $data)) {
            return $data[$class];
        }
        return 0;
    }

    /**
     * @use 复位
     */
    public function restore(): void
    {
        Log::info('复位任务排程文件。');
        File::del($this->repository);
        $this->repository = '';
        $this->init();
    }

}