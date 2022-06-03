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

namespace Bhp\Util\Resource;

use Grasmash\Expander\Expander;
use Grasmash\Expander\Stringifier;
use JBZoo\Data\Data;
use function JBZoo\Data\data;
use function JBZoo\Data\ini;
use function JBZoo\Data\phpArray;
use function JBZoo\Data\json;
use function JBZoo\Data\yml;

class Resource extends Collection
{
    protected const FORMAT_INI = 'ini';
    protected const FORMAT_PHP = 'php';
    protected const FORMAT_YAML = 'yaml';
    protected const FORMAT_YML = 'yml';
    protected const FORMAT_JSON = 'json';

    /**
     * @var Data
     */
    protected Data $config;

    /**
     * @var string
     */
    protected string $file_path;

    /**
     * @var string
     */
    protected string $parser;

    /**
     * @use 加载资源文件
     * @param string|array $file_path
     * @param string $parser
     * @return Resource
     */
    public function loadF(string|array $file_path, string $parser): Resource
    {
        // 存储文件路径
        $this->file_path = $file_path;
        // 存储解析器
        $this->parser = $parser;
        // 加载文件
        $this->config = $this->switchParser($file_path, $parser);
        // 加载数据
        $this->reload();
        //
        return $this;
    }

    /**
     * @use 切换解析器
     * @param string $filepath
     * @param string $format
     * @return Data
     */
    protected function switchParser(string $filepath, string $format): Data
    {
        return match ($format) {
            Resource::FORMAT_INI => ini($filepath),
            Resource::FORMAT_PHP => phpArray($filepath),
            Resource::FORMAT_YML, Resource::FORMAT_YAML => yml($filepath),
            Resource::FORMAT_JSON => json($filepath),
            default => data($filepath),
        };
    }

    /**
     * @use 清空并重载数据
     * @return Resource
     */
    protected function reload(): Resource
    {
        // 转换一次 ${}
        $expander = new Expander();
        $expander->setStringifier(new Stringifier());
        $expanded = $expander->expandArrayProperties($this->config->getArrayCopy());
        // 清除数据
        $this->clear();
        // 加载数据
        $this->load($expanded);
        //
        return $this;
    }


}
 