<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Plugin;

trait BasePluginInfo
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected array $info_template = [
        'hook' => __CLASS__, // Hook名称
        'name' => 'name', // 插件名称
        'version' => 'version', // 插件版本
        'desc' => 'desc', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 0, // 插件优先级
        'cycle' => 'cycle', // 运行周期
    ];

    /**
     * @var array|null
     */
    protected ?array $info;

    /**
     * @use 设置Hook
     * @param string $value
     * @param string $key
     * @return $this
     */
    protected function setHook(string $value, string $key = 'hook'): static
    {
        $this->info[$key] = $value;
        return $this;
    }

    /**
     * @use 设置名称
     * @param string $value
     * @param string $key
     * @return $this
     */
    protected function setName(string $value, string $key = 'name'): static
    {
        $this->info[$key] = $value;
        return $this;
    }

    /**
     * @use 设置版本
     * @param string $value
     * @param string $key
     * @return $this
     */
    protected function setVersion(string $value, string $key = 'version'): static
    {
        $this->info[$key] = $value;
        return $this;
    }

    /**
     * @use 设置描述
     * @param string $value
     * @param string $key
     * @return $this
     */
    protected function setDesc(string $value, string $key = 'desc'): static
    {
        $this->info[$key] = $value;
        return $this;
    }

    /**
     * @use 设置优先级
     * @param int $value
     * @param string $key
     * @return $this
     */
    protected function setPriority(int $value, string $key = 'priority'): static
    {
        $this->info[$key] = $value;
        return $this;
    }

    /**
     * @use 设置运行周期
     * @param string $value
     * @param string $key
     * @return $this
     */
    protected function setCycle(string $value, string $key = 'name'): static
    {
        $this->info[$key] = $value;
        return $this;
    }

    /**
     * @use 返回插件信息
     * @return array|string[]
     */
    public function getPluginInfo(): array
    {
        return $this->info;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $name;
    }

    /**
     * @param $name
     * @param $value
     * @return string
     */
    public function __set($name, $value)
    {
        return $name . $value;
    }

}