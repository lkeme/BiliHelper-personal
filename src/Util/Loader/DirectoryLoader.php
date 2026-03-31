<?php declare(strict_types=1);

namespace Bhp\Util\Loader;

final class DirectoryLoader
{
    /**
     * @param string[] $exclude
     */
    public static function requireDir(string $dir, array $exclude = []): void
    {
        $handle = opendir($dir);
        if ($handle === false) {
            return;
        }

        while (false !== ($file = readdir($handle))) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (in_array($file, $exclude, true)) {
                continue;
            }

            $filepath = $dir . '/' . $file;
            if (filetype($filepath) === 'dir') {
                self::requireDir($filepath, $exclude);
                continue;
            }

            include_once $filepath;
        }

        closedir($handle);
    }
}
