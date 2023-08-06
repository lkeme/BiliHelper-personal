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

namespace Bhp\Request;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as PRequest;

class MultiRequest
{
    /**
     * @var Client
     */
    protected Client $client;

    protected array $headers = [];
    protected array $options = [];
    protected Closure $successCallback;
    protected Closure $errorCallback;
    protected array $urls = [];
    protected string $method;
    protected int $concurrency = 10;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param Client $client
     * @return MultiRequest
     */
    public static function newMultiRequest(Client $client): MultiRequest
    {
        return new self($client);
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * @param $options
     * @return $this
     */
    public function withOptions($options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param $concurrency
     * @return $this
     */
    public function concurrency($concurrency): static
    {
        $this->concurrency = $concurrency;
        return $this;
    }

    /**
     * @param Closure $success
     * @return $this
     */
    public function success(Closure $success): static
    {
        $this->successCallback = $success;
        return $this;
    }

    /**
     * @param Closure $error
     * @return $this
     */
    public function error(Closure $error): static
    {
        $this->errorCallback = $error;
        return $this;
    }

    /**
     * @param array $urls
     * @return $this
     */
    public function urls(array $urls): static
    {
        $this->urls = $urls;
        return $this;
    }

    /**
     * @return void
     */
    public function get(): void
    {
        $this->method = 'GET';
        $this->send();
    }

    /**
     * @return void
     */
    public function post(): void
    {
        $this->method = 'POST';
        $this->send();
    }

    /**
     * @return void
     */
    protected function send(): void
    {
        $client = $this->client;

        $requests = function ($urls) use ($client) {
            foreach ($urls as $url) {
                if (is_string($url)) {
                    yield new PRequest($this->method, $url, $this->headers);
                } else {
                    yield $url;
                }
            }
        };

        $pool = new Pool($client, $requests($this->urls), [
            'concurrency' => $this->concurrency,
            'fulfilled' => $this->successCallback,
            'rejected' => $this->errorCallback,
            'options' => $this->options
        ]);

        $promise = $pool->promise();
        $promise->wait();

    }

}
