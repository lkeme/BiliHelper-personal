<?php

/**
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 *
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *  &   ／l、
 *    （ﾟ､ ｡ ７
 *   　\、ﾞ ~ヽ   *
 *   　じしf_, )ノ
 *
 */


namespace BiliHelper\Plugins;

abstract class BasePlugin
{
    private object $_view;

    /**
     * @use 视图
     * @return object
     */
    public function getView(): object
    {
        return $this->_view;
    }

    /**
     * @use 视图
     * @param mixed $view
     */
    public function setView(mixed $view): void
    {
        $this->_view = $view;
    }

    /**
     * @use 渲染视图
     * @param $view
     * @param array $params
     */
    public function render($view, array $params = [])
    {

    }

    /**
     * @use 渲染视图文件
     * @param $file
     * @param array $params
     */
    public function renderFile($file, array $params = [])
    {
    }

    /**
     * @use 视图路径
     */
    public function getViewPath()
    {
    }

    /**
     * @use 安装
     * @return bool
     */
    public function install(): bool
    {
        return true;
    }

    /**
     * @use 卸载
     * @return bool
     */
    public function uninstall(): bool
    {
        return true;
    }

    /**
     * @use 开启
     * @return bool
     */
    public function open(): bool
    {
        return true;
    }

    /**
     * @use 关闭
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @use 更新
     * @return bool
     */
    public function upgrade(): bool
    {
        return true;
    }
}