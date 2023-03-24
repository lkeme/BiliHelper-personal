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

namespace Bhp\Util\Os;

class Path
{
    /**
     * Folder Permissions
     * @param string $path
     * @param int $permissions
     * @return void
     * 0600 所有者可读写，其他人没有任何权限
     * 0644 所有者可读写，其他人可读
     * 0755 所有者有所有权限，其他所有人可读和执行
     * 0740 所有者有所有权限，所有者所在的组可读
     * 0777 所有权限
     */
    public static function SetFolderPermissions(string $path, int $permissions = 0777): void
    {
        if (!file_exists($path)) {
            chmod($path, $permissions);
        }
    }

    /**
     * Create Folder
     * @param string $path
     * @param int $permissions
     * @return void
     * 0600 所有者可读写，其他人没有任何权限
     * 0644 所有者可读写，其他人可读
     * 0755 所有者有所有权限，其他所有人可读和执行
     * 0740 所有者有所有权限，所有者所在的组可读
     * 0777 所有权限
     */
    public static function CreateFolder(string $path, int $permissions = 0777): void
    {
        if (!file_exists($path)) {
            mkdir($path, $permissions);
        }
    }


}
