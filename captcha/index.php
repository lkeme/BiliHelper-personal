<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *  Source: Colter23/geetest-validator
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */
class JsonFileManager
{
    private string $filename;

    /**
     * 初始化 JsonFileManager
     * @param mixed $filename
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * 处理read
     * @return array
     */
    public function read(): array
    {
        if (!is_file($this->filename)) {
            return [];
        }

        $handle = fopen($this->filename, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }

            $json = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if ($json === false || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * 处理write
     * @param array $data
     * @return void
     */
    public function write(array $data): void
    {
        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $json = '{}';
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * 原子更新 JSON 内容，避免并发写入时覆盖彼此的数据
     * @param callable $updater
     * @return array
     */
    public function update(callable $updater): array
    {
        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return [];
            }

            $json = stream_get_contents($handle);
            $data = trim((string)$json) === '' ? [] : json_decode((string)$json, true);
            if (!is_array($data)) {
                $data = [];
            }

            $updated = $updater($data);
            if (!is_array($updated)) {
                $updated = $data;
            }

            $encoded = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                $encoded = '{}';
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $encoded);
            fflush($handle);

            return $updated;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}


class HttpServer
{
    private JsonFileManager $json;
    protected string $filename = __DIR__ . '/data.json';

    /**
     * 初始化 HttpServer
     */
    public function __construct()
    {
        $this->createFile();
        $this->json = new JsonFileManager($this->filename);
    }

    /**
     * 处理start
     * @return void
     */
    public function start(): void
    {
        // 获取请求路径
        $requestUri = $_SERVER['REQUEST_URI'];
        $path = parse_url($requestUri, PHP_URL_PATH);
//        $queryString = parse_url($requestUri, PHP_URL_QUERY);

        // 否则处理其他API请求
        $method = $_SERVER['REQUEST_METHOD'];

        $this->handleRequest($path, $method);
    }

    /**
     * 创建文件
     * @return bool
     */
    protected function createFile(): bool
    {
        if (file_exists($this->filename)) {
            return false;
        }
        // 判断文件类型是否为目录, 如果不存在则创建
        if (!file_exists(dirname($this->filename))) {
            mkdir(dirname($this->filename), 0777, true);
        }
        if (touch($this->filename)) {
            return true;
        }
        return false;
    }

    /**
     * @param $path
     * @param $method
     * @return void
     */
    protected function handleRequest(?string $path, string $method): void
    {
        // 如果是 /geetest 路径，返回静态页面
        if ($path === '/geetest' && $method === 'GET') {
            header('Content-Type: text/html; charset=utf-8');
            include __DIR__ . '/static/index.html';
            exit();
        }

        // 如果是 /geetest 路径，POST请求，处理验证
        if ($path === '/feedback' && $method === 'POST') {
            $challenge = $this->requestString($_POST, 'challenge');
            $new_challenge = $this->requestString($_POST, 'new_challenge');
            $validate = $this->requestString($_POST, 'validate');
            $seccode = $this->requestString($_POST, 'seccode');
            if (
                !$this->isValidChallenge($challenge)
                || !$this->isReasonableField($new_challenge, 256)
                || !$this->isReasonableField($validate, 512)
                || !$this->isReasonableField($seccode, 1024)
            ) {
                $this->toResponse(10002, '参数错误');
                return;
            }

            $this->json->update(function (array $data) use ($challenge, $new_challenge, $validate, $seccode): array {
                $data[$challenge] = [
                    'challenge' => $new_challenge,
                    'validate' => $validate,
                    'seccode' => $seccode,
                ];

                return $data;
            });
            $this->toResponse(10003);
            return;
        }
        if ($path === '/fetch' && $method === 'GET') {
            $challenge = $this->requestString($_GET, 'challenge');
            if (!$this->isValidChallenge($challenge)) {
                $this->toResponse(10001, '暂未获取到验证结果');
                return;
            }

            $data = $this->json->read();
            if (empty($data[$challenge])) {
                $this->toResponse(10001, '暂未获取到验证结果');
            } else {
                $this->toResponse(10000, '成功获取到验证结果', $data[$challenge]);
            }
            return;
        }
        // other
        http_response_code(404);
        $this->toResponse(404, 'Not Found');
    }

    /**
     * @param int $code
     * @param string $message
     * @param array $data
     * @return void
     */
    protected function toResponse(int $code = 200, string $message = 'success', array $data = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
//        http_response_code($code);
        echo json_encode(['code' => $code, 'message' => $message, 'data' => $data]);
    }

    /**
     * @param array<string, mixed> $source
     * @param string $key
     * @return string
     */
    protected function requestString(array $source, string $key): string
    {
        $value = $source[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string)$value);
    }

    /**
     * @param string $value
     * @return bool
     */
    protected function isValidChallenge(string $value): bool
    {
        return preg_match('/^[a-f0-9]{32}$/i', $value) === 1;
    }

    /**
     * @param string $value
     * @param int $maxLength
     * @return bool
     */
    protected function isReasonableField(string $value, int $maxLength): bool
    {
        return $value !== '' && strlen($value) <= $maxLength;
    }


}

(new HttpServer)->start();

