<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 *  Resource: https://github.com/XcantloadX
 */

namespace BiliHelper\Core;

class HttpClient
{
//    private $ch;
//    private $ret;
//    private $form;
//    private $headers;
//    private $url;
//    private $query;
//
//    public function __construct(string $url)
//    {
//        $this->ch = curl_init();
//        $this->url = $url;
//        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false); //忽略 HTTPS 证书错误
//        $this->setUA("Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.96 Safari/537.36");
//    }
//
//    function __destruct()
//    {
//        //防止内存泄露
//        curl_close($this->ch);
//    }
//
//    /**
//     * @use 设置UA
//     * @param string $ua
//     * @return $this
//     */
//    public function setUA(string $ua): HttpClient
//    {
//        curl_setopt($this->ch, CURLOPT_USERAGENT, $ua); //设置 UA
//        return $this;
//    }
//
//    /**
//     * @use 添加Header
//     * @param string $header
//     * @return $this
//     */
//    public function addHeader(string $header): HttpClient
//    {
//        array_push($this->headers, $header);
//        return $this;
//    }
//
//    /**
//     * @use 添加Headers
//     * @param array $headers
//     * @return $this
//     */
//    public function addHeaders(array $headers): HttpClient
//    {
//        foreach ($headers as $key => $value) {
//            array_push($this->headers, "{$key}: {$value}");
//        }
//        return $this;
//    }
//
//    /**
//     * @use 设置Cookie
//     * @param string $cookie
//     * @return $this
//     */
//    public function setCookie(string $cookie): HttpClient
//    {
//        curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
//        return $this;
//    }
//
//    /**
//     * @use 设置 url 参数
//     */
//    public function buildQuery(array $query): HttpClient
//    {
//        $this->query = http_build_query($query);
//        return $this;
//    }
//
//    /**
//     * @use 自动将 json 文本解码
//     */
//    public function asJSON(): object
//    {
//        return json_decode($this->ret);
//    }
//
//    /**
//     * @use 获取返回结果
//     */
//    public function asString(): string
//    {
//        return $this->ret;
//    }
//
//    /**
//     * @use 构造POST表单
//     * @param array $form
//     * @return $this
//     */
//    public function buildPostForm(array $form): HttpClient
//    {
//        $this->form = http_build_query($form);
//
//        return $this;
//    }
//
//    /**
//     * @use 发送 Get 请求
//     */
//    public function get(): HttpClient
//    {
//        curl_setopt($this->ch, CURLOPT_URL, $this->url . "?" . $this->query);
//        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); //返回内容储存到变量中
//        curl_setopt($this->ch, CURLOPT_HEADER, $this->headers);
//        $this->ret = curl_exec($this->ch);
//        return $this;
//    }
//
//    /**
//     * @use 发送 POST 请求
//     * @param string|null $data 要 POST 的数据
//     * @return \HttpClient
//     */
//    public function post(string $data = null): HttpClient
//    {
//        curl_setopt($this->ch, CURLOPT_URL, $this->url . "?" . $this->query);
//        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); //返回内容储存到变量中
//        curl_setopt($this->ch, CURLOPT_POST, true); // 发送 POST 请求
//        curl_setopt($this->ch, CURLOPT_HEADER, $this->headers);
//        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
//        $this->ret = curl_exec($this->ch);
//        return $this;
//    }
//
//
//    /**
//     * @use POST表单
//     * @param array $form
//     * @return $this
//     */
//    public function postForm(array $form): HttpClient
//    {
//        return $this->post(http_build_query($form));
//    }
}

///**
// * @use HttpClient
// * @param string $url
// * @return \BiliHelper\Core\HttpClient
// */
//function newHttp(string $url): HttpClient
//{
//    return new HttpClient($url);
//}

