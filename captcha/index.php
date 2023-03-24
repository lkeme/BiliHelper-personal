<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
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

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function read(): array
    {
        $json = file_get_contents($this->filename);
        if (empty($json)) {
            return [];
        }
        return json_decode($json, true);
    }

    public function write(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->filename, $json);
    }
}


class HttpServer
{
    private JsonFileManager $json;
    protected string $filename = 'data.json';

    public function __construct()
    {
        $this->createFile();
        $this->json = new JsonFileManager($this->filename);
    }

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
    protected function handleRequest($path, $method): void
    {
        // 如果是 /geetest 路径，返回静态页面
        if ($path == '/geetest' && $method === 'GET') {
            header('Content-Type: text/html; charset=utf-8');
            include('static/index.html');
            exit();
        }

        // 如果是 /geetest 路径，POST请求，处理验证
        if ($path === '/feedback' && $method === 'POST') {
            // 获取参数，从json里的读取
            $data = $this->json->read();
            $challenge = $_POST['challenge'];
            $validate = $_POST['validate'];
            $seccode = $_POST['seccode'];
            $data[$challenge] = [
                'challenge' => $challenge,
                'validate' => $validate,
                'seccode' => $seccode,
            ];
            $this->json->write($data);
             $this->toResponse(10003);
            return;
        }
        if ($path === '/fetch' && $method === 'GET') {
            // 获取参数，从json里的读取
            $challenge = $_GET['challenge'];
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
        header('Content-Type: application/json');
//        http_response_code($code);
        echo json_encode(['code' => $code, 'message' => $message, 'data' => $data]);
    }


}

(new HttpServer)->start();

