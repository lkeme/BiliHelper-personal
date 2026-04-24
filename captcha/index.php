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
    private const MANUAL_FLOW_KEY = '__manual_flows';

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
        if ($path === '/assist' && $method === 'GET') {
            header('Content-Type: text/html; charset=utf-8');
            include __DIR__ . '/static/index.html';
            exit();
        }

        // 如果是 /geetest 路径，返回静态页面
        if ($path === '/geetest' && $method === 'GET') {
            header('Content-Type: text/html; charset=utf-8');
            include __DIR__ . '/static/index.html';
            exit();
        }

        if ($path === '/api/manual-flow/open' && $method === 'POST') {
            $flowId = $this->requestString($_POST, 'id');
            $type = $this->requestString($_POST, 'type');
            $title = $this->requestString($_POST, 'title');
            $message = $this->requestString($_POST, 'message');
            $maskedPhone = $this->requestString($_POST, 'masked_phone');
            $gt = $this->requestString($_POST, 'gt');
            $challenge = $this->requestString($_POST, 'challenge');
            $expiresAt = (int)$this->requestString($_POST, 'expires_at');
            if (
                !$this->isValidFlowId($flowId)
                || !$this->isValidFlowType($type)
                || !$this->isReasonableOptionalField($title, 80)
                || !$this->isReasonableOptionalField($message, 300)
                || !$this->isReasonableOptionalField($maskedPhone, 50)
                || ($gt !== '' && !$this->isValidChallenge($gt))
                || ($challenge !== '' && !$this->isValidChallenge($challenge))
                || $expiresAt <= time()
            ) {
                $this->toResponse(10011, '登录助手流程参数错误');
                return;
            }

            $data = $this->json->update(function (array $data) use ($flowId, $type, $title, $message, $maskedPhone, $gt, $challenge, $expiresAt): array {
                $data = $this->pruneManualFlows($data);
                $flows = $this->manualFlows($data);
                $flows[$flowId] = [
                    'id' => $flowId,
                    'type' => $type,
                    'status' => 'pending',
                    'title' => $title,
                    'message' => $message,
                    'masked_phone' => $maskedPhone,
                    'gt' => $gt,
                    'challenge' => $challenge,
                    'submitted_code' => '',
                    'result' => [],
                    'created_at' => time(),
                    'expires_at' => $expiresAt,
                ];
                $data[self::MANUAL_FLOW_KEY] = $flows;

                return $data;
            });

            $flows = $this->manualFlows($data);
            $this->toResponse(10010, '登录助手流程已创建', $flows[$flowId] ?? []);
            return;
        }

        if ($path === '/api/manual-flow' && $method === 'GET') {
            $flowId = $this->requestString($_GET, 'id');
            if (!$this->isValidFlowId($flowId)) {
                $this->toResponse(10021, '登录助手流程不存在');
                return;
            }

            $data = $this->json->update(fn (array $data): array => $this->pruneManualFlows($data));
            $flows = $this->manualFlows($data);
            if (!isset($flows[$flowId]) || !is_array($flows[$flowId])) {
                $this->toResponse(10021, '登录助手流程不存在');
                return;
            }

            $flow = $flows[$flowId];
            if (($flow['expires_at'] ?? 0) < time()) {
                $flow['status'] = 'expired';
            }

            $this->toResponse(10020, '成功获取登录助手流程', $flow);
            return;
        }

        if ($path === '/api/manual-flow/code' && $method === 'POST') {
            $flowId = $this->requestString($_POST, 'id');
            $code = $this->requestString($_POST, 'code');
            if (!$this->isValidFlowId($flowId) || !$this->isReasonableField($code, 32)) {
                $this->toResponse(10031, '短信验证码参数错误');
                return;
            }

            $updated = false;
            $data = $this->json->update(function (array $data) use ($flowId, $code, &$updated): array {
                $data = $this->pruneManualFlows($data);
                $flows = $this->manualFlows($data);
                if (!isset($flows[$flowId]) || !is_array($flows[$flowId])) {
                    return $data;
                }

                if (($flows[$flowId]['expires_at'] ?? 0) < time()) {
                    $flows[$flowId]['status'] = 'expired';
                } else {
                    $flows[$flowId]['status'] = 'submitted';
                    $flows[$flowId]['submitted_code'] = $code;
                }
                $updated = true;
                $data[self::MANUAL_FLOW_KEY] = $flows;

                return $data;
            });

            if (!$updated) {
                $this->toResponse(10021, '登录助手流程不存在');
                return;
            }

            $flows = $this->manualFlows($data);
            $this->toResponse(10030, '短信验证码提交成功', $flows[$flowId] ?? []);
            return;
        }

        if ($path === '/api/manual-flow/geetest' && $method === 'POST') {
            $flowId = $this->requestString($_POST, 'id');
            $challenge = $this->requestString($_POST, 'challenge');
            $newChallenge = $this->requestString($_POST, 'new_challenge');
            $validate = $this->requestString($_POST, 'validate');
            $seccode = $this->requestString($_POST, 'seccode');
            if (
                !$this->isValidFlowId($flowId)
                || !$this->isValidChallenge($challenge)
                || !$this->isReasonableField($newChallenge, 256)
                || !$this->isReasonableField($validate, 512)
                || !$this->isReasonableField($seccode, 1024)
            ) {
                $this->toResponse(10041, '行为验证码参数错误');
                return;
            }

            $updated = false;
            $data = $this->json->update(function (array $data) use ($flowId, $challenge, $newChallenge, $validate, $seccode, &$updated): array {
                $data = $this->pruneManualFlows($data);
                $flows = $this->manualFlows($data);
                if (!isset($flows[$flowId]) || !is_array($flows[$flowId])) {
                    return $data;
                }

                if (($flows[$flowId]['expires_at'] ?? 0) < time()) {
                    $flows[$flowId]['status'] = 'expired';
                } else {
                    $flows[$flowId]['status'] = 'resolved';
                    $flows[$flowId]['result'] = [
                        'challenge' => $newChallenge,
                        'validate' => $validate,
                        'seccode' => $seccode,
                    ];
                    $data[$challenge] = $flows[$flowId]['result'];
                }
                $updated = true;
                $data[self::MANUAL_FLOW_KEY] = $flows;

                return $data;
            });

            if (!$updated) {
                $this->toResponse(10021, '登录助手流程不存在');
                return;
            }

            $flows = $this->manualFlows($data);
            $this->toResponse(10040, '行为验证码提交成功', $flows[$flowId] ?? []);
            return;
        }

        if ($path === '/api/manual-flow/clear' && $method === 'POST') {
            $flowId = $this->requestString($_POST, 'id');
            if (!$this->isValidFlowId($flowId)) {
                $this->toResponse(10021, '登录助手流程不存在');
                return;
            }

            $this->json->update(function (array $data) use ($flowId): array {
                $flows = $this->manualFlows($data);
                unset($flows[$flowId]);
                $data[self::MANUAL_FLOW_KEY] = $flows;

                return $data;
            });
            $this->toResponse(10050, '登录助手流程已清理');
            return;
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<string, mixed>>
     */
    protected function manualFlows(array $data): array
    {
        $flows = $data[self::MANUAL_FLOW_KEY] ?? [];
        return is_array($flows) ? $flows : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function pruneManualFlows(array $data): array
    {
        $flows = $this->manualFlows($data);
        $now = time();
        foreach ($flows as $flowId => $flow) {
            if (!is_string($flowId) || !is_array($flow)) {
                unset($flows[$flowId]);
                continue;
            }

            $expiresAt = isset($flow['expires_at']) && is_numeric($flow['expires_at']) ? (int)$flow['expires_at'] : 0;
            if ($expiresAt > 0 && $expiresAt >= $now) {
                continue;
            }

            unset($flows[$flowId]);
        }

        $data[self::MANUAL_FLOW_KEY] = $flows;
        return $data;
    }

    protected function isValidFlowId(string $value): bool
    {
        return preg_match('/^[a-f0-9]{32}$/i', $value) === 1;
    }

    protected function isValidFlowType(string $value): bool
    {
        return in_array($value, ['geetest', 'sms_code', 'risk_sms_code'], true);
    }

    protected function isReasonableOptionalField(string $value, int $maxLength): bool
    {
        return $value === '' || strlen($value) <= $maxLength;
    }


}

(new HttpServer)->start();

