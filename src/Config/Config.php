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

namespace Bhp\Config;

use Bhp\Profile\ProfileContext;
use Bhp\Util\Resource\BaseResource;

class Config extends BaseResource
{
    /**
     * 初始化 Config
     * @param ProfileContext $profileContext
     * @param string $filename
     */
    public function __construct(
        private readonly ProfileContext $profileContext,
        string $filename = 'user.ini',
    )
    {
        $targetPath = $this->getFilePath($filename);
        $schema = new ConfigSchemaDefinition();
        $result = (new ConfigTemplateSynchronizer())->synchronize($schema->exampleConfigPath(), $targetPath);
        if ($result->changed) {
            $message = '配置模板已同步: ' . basename($targetPath);
            if ($result->backupPath !== null) {
                $message .= ' (backup: ' . basename($result->backupPath) . ')';
            }

            fwrite(STDOUT, $message . PHP_EOL);
        }

        $this->loadResource($filename, 'ini');
        (new ConfigRuntimeValidator($this, $schema))->validate();
    }

    /**
     * 重写获取路径
     * @param string $filename
     * @return string
     */
    protected function getFilePath(string $filename): string
    {
        return str_replace("\\", "/", $this->profileContext->configPath() . $filename);
    }
}
