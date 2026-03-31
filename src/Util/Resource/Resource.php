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

namespace Bhp\Util\Resource;

use Symfony\Component\Yaml\Yaml;

class Resource extends Collection
{
    protected const FORMAT_INI = 'ini';
    protected const FORMAT_PHP = 'php';
    protected const FORMAT_YAML = 'yaml';
    protected const FORMAT_YML = 'yml';
    protected const FORMAT_JSON = 'json';

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected string $file_path = '';
    protected string $parser = '';

    public function loadF(string|array $file_path, string $parser): Resource
    {
        if (!is_string($file_path)) {
            $this->config = [];
            $this->file_path = '';
            $this->parser = $parser;
            $this->reload();

            return $this;
        }

        $this->file_path = $file_path;
        $this->parser = $parser;
        $this->config = $this->switchParser($file_path, $parser);
        $this->reload();

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function switchParser(string $filepath, string $format): array
    {
        return match ($format) {
            self::FORMAT_INI => $this->parseIni($filepath),
            self::FORMAT_JSON => $this->parseJson($filepath),
            self::FORMAT_YML, self::FORMAT_YAML => $this->parseYaml($filepath),
            self::FORMAT_PHP => $this->parsePhp($filepath),
            default => [],
        };
    }

    protected function reload(): Resource
    {
        $expanded = $this->expandArrayProperties($this->config);
        $this->clear();
        $this->load($expanded);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseIni(string $filepath): array
    {
        $data = parse_ini_file($filepath, true, INI_SCANNER_TYPED);

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseJson(string $filepath): array
    {
        $content = file_get_contents($filepath);
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseYaml(string $filepath): array
    {
        $data = Yaml::parseFile($filepath);

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parsePhp(string $filepath): array
    {
        $data = include $filepath;

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function expandArrayProperties(array $data): array
    {
        $expanded = $data;

        for ($i = 0; $i < 5; $i++) {
            $next = $this->expandValue($expanded, $expanded);
            if ($next === $expanded) {
                break;
            }

            $expanded = $next;
        }

        return $expanded;
    }

    /**
     * @param array<string, mixed> $root
     */
    protected function expandValue(mixed $value, array $root): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->expandValue($item, $root);
            }

            return $result;
        }

        if (!is_string($value) || !str_contains($value, '${')) {
            return $value;
        }

        return preg_replace_callback('/\$\{([^}]+)\}/', function (array $matches) use ($root) {
            $resolved = $this->getByPath($root, trim((string)$matches[1]));

            if (is_scalar($resolved) || $resolved === null) {
                return (string)$resolved;
            }

            return $matches[0];
        }, $value) ?? $value;
    }
}
