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

use Bhp\Api\Passport\ApiCaptcha;
use Bhp\Api\Passport\ApiLogin;
use Bhp\Api\Passport\ApiOauth2;
use Bhp\Api\PassportTv\ApiQrcode;
use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\User\User;
use Bhp\Util\Common\Common;
use Bhp\Util\Qrcode\Qrcode;
use JetBrains\PhpStorm\NoReturn;

class Login extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'Login', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '登录', // 插件描述
        'author' => 'Lkeme', // 作者
        'priority' => 1001, // 插件优先级
        'cycle' => '2(小时)', // 运行周期
    ];

    /**
     * @var string|null
     */
    protected ?string $username = '';
    /**
     * @var string|null
     */
    protected ?string $password = '';

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        //
        TimeLock::initTimeLock();
        //
        Cache::initCache();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        //
        if (TimeLock::getTimes() && TimeLock::getTimes() < time()) {
            TimeLock::setTimes(7200);
            $this->keepLogin();
        }
        //
        if (!TimeLock::getTimes()) {
            $this->initLogin();
            TimeLock::setTimes(3600);
        }
    }

    /**
     * 初始化登录
     * @return void
     */
    protected function initLogin(): void
    {
        //
        $token = getU('access_token');
        $r_token = getU('refresh_token');
        // Token不存在的情况\直接调用登录
        Log::info('启动登录程序');
        if (!$token || !$r_token) {
            Log::info('准备载入登录令牌');
            $this->login();
        }
        // Token存在\校验有效性\否则调用登录
        $this->keepLogin();
    }

    /**
     * 登录控制中心
     * @return void
     */
    protected function login(): void
    {
        $this->checkLogin();
        //
        switch ((int)getConf('login_mode.mode')) {
            case 1:
                // 账密模式
                $this->accountLogin();
                break;
            case 2:
                // 短信验证码模式
                $this->smsLogin();
                break;
            case 3:
                // 二维码模式
                $this->qrcodeLogin();
                break;
            case 4:
                // 行为验证码模式(暂未开放)
                // self::captchaLogin();
                failExit('此登录模式暂未开放');
            default:
                failExit('登录模式配置错误');
        }
    }

    /**
     * 保持认证
     */
    protected function keepLogin(): void
    {
        //
        $token = getU('access_token');
        $r_token = getU('refresh_token');
        // Token存在\校验有效性\否则调用登录
        $this->keepAlive($token, $r_token);
    }

    /**
     * 保活处理
     * @param string $token
     * @param string $r_token
     * @return bool
     */
    protected function keepAlive(string $token, string $r_token): bool
    {
        Log::info('检查登录令牌有效性');
        if (!$this->validateToken($token)) {
            Log::warning('登录令牌失效过期或需要保活');
            Log::info('申请更换登录令牌中...');
            if (!$this->refreshToken($token, $r_token)) {
                Log::warning('无效的登录令牌，尝试重新申请...');
                $this->login();
            }
        }
        //
        $token = getU('access_token');
        $this->myInfo($token);

        return true;
    }

    /**
     * 校验令牌信息
     * @param string $token
     * @return bool
     */
    protected function validateToken(string $token): bool
    {
        // {"code":0,"message":"0","ttl":1,"data":{"mid":"<user mid>","access_token":"<current token>","expires_in":9787360,"refresh":true}}
        $response = ApiOauth2::tokenInfoNew($token);
        //
        if (isset($response['code']) && $response['code']) {
            Log::error('检查令牌失败', ['msg' => $response['message']]);
            return false;
        }
        Log::notice('令牌有效期: ' . date('Y-m-d H:i:s', time() + $response['data']['expires_in']));
        // 保活
        return !$response['data']['refresh'] && $response['data']['expires_in'] > 14400;
    }

    /**
     * 刷新token
     * @param string $token
     * @param string $r_token
     * @return bool
     */
    protected function refreshToken(string $token, string $r_token): bool
    {
        $response = ApiOauth2::tokenRefreshNew($token, $r_token);
        // {"message":"user not login","ts":1593111694,"code":-101}
        if (isset($response['code']) && $response['code']) {
            Log::error('重新生成令牌失败', ['msg' => $response['message']]);
            return false;
        }
        Log::info('重新令牌生成完毕');
        $this->updateLoginInfo($response);
        Log::info('重置信息配置完毕');
        return true;
    }

    /**
     * 登录信息
     * @param string $token
     * @return bool
     */
    protected function myInfo(string $token): bool
    {
        $response = ApiOauth2::myInfo($token);
        if (isset($response['code']) && $response['code']) {
            Log::error('获取登录信息失败', ['msg' => $response['message']]);
            return false;
        }
        Log::info('获取登录信息成功');
        return true;
    }

    /**
     * 更新登录信息
     * @param array $data
     */
    protected function updateLoginInfo(array $data): void
    {
        //
        $access_token = $data['data']['token_info']['access_token'];
        $this->updateInfo('access_token', $access_token);
        //
        $refresh_token = $data['data']['token_info']['refresh_token'];
        $this->updateInfo('refresh_token', $refresh_token);
        //
        $cookie = $this->formatCookie($data['data']['cookie_info']['cookies']);
        $this->updateInfo('cookie', $cookie);
        //
        $user = User::parseCookie();
        $this->updateInfo('uid', $user['uid'], false);
        $this->updateInfo('csrf', $user['csrf'], false);
        $this->updateInfo('sid', $user['sid'], false);
        //
        // $this->updateInfo('username',$this->username);
        // $this->updateInfo('password',$this->password);
    }

    /**
     * 更新Tv登录信息
     * @param array $data
     */
    protected function updateTvLoginInfo(array $data): void
    {
        //
        $access_token = $data['data']['access_token'];
        $this->updateInfo('access_token', $access_token);
        //
        $refresh_token = $data['data']['refresh_token'];
        $this->updateInfo('refresh_token', $refresh_token);
        //
        //
        $cookie = $this->token2Cookie($access_token);
        $this->updateInfo('cookie', $cookie);
        //
        $user = User::parseCookie();
        $this->updateInfo('uid', $user['uid'], false);
        $this->updateInfo('csrf', $user['csrf'], false);
        $this->updateInfo('sid', $user['sid'], false);
        //
        // $this->updateInfo('username',$this->username);
        // $this->updateInfo('password',$this->password);
    }

    /**
     * 更新信息
     * @param string $key
     * @param mixed $value
     * @param bool $print
     * @param bool $hide
     * @return void
     */
    protected function updateInfo(string $key, mixed $value, bool $print = true, bool $hide = true): void
    {
        setU($key, $value);
        if ($print) {
            Log::info(" > $key: " . ($hide ? Common::replaceStar($value, 6, 6) : $value));
        }
    }

    /**
     * 格式化Cookie
     * @param array $cookies
     * @return string
     */
    protected function formatCookie(array $cookies): string
    {
        $c = '';
        foreach ($cookies as $cookie) {
            $c .= $cookie['name'] . '=' . $cookie['value'] . ';';
        }
        return $c;
    }

    /**
     * 账密登录
     * @param string $validate
     * @param string $challenge
     * @param string $mode
     * @return void
     */
    protected function accountLogin(string $validate = '', string $challenge = '', string $mode = '账密模式'): void
    {
        Log::info("尝试 $mode 登录");
        // {"ts":1593079322,"code":-629,"message":"账号或者密码错误"}
        // {"ts":1593082268,"code":-105,"data":{"url":"https://passport.bilibili.com/register/verification.html?success=1&gt=b6e5b7fad7ecd37f465838689732e788&challenge=7efb4020b22c0a9ac124aea624e11ad7&ct=1&hash=7fa8282ad93047a4d6fe6111c93b308a"},"message":"验证码错误"}
        // {"ts":1593082432,"code":0,"data":{"status":0,"token_info":{"mid":123456,"access_token":"123123","refresh_token":"123123","expires_in":2592000},"cookie_info":{"cookies":[{"name":"bili_jct","value":"123123","http_only":0,"expires":1595674432},{"name":"DedeUserID","value":"123456","http_only":0,"expires":1595674432},{"name":"DedeUserID__ckMd5","value":"123123","http_only":0,"expires":1595674432},{"name":"sid","value":"bd6aagp7","http_only":0,"expires":1595674432},{"name":"SESSDATA","value":"6d74d850%123%2Cf0e36b61","http_only":1,"expires":1595674432}],"domains":[".bilibili.com",".biligame.com",".bigfunapp.cn"]},"sso":["https://passport.bilibili.com/api/v2/sso","https://passport.biligame.com/api/v2/sso","https://passport.bigfunapp.cn/api/v2/sso"]}}
        // {"ts":1610254019,"code":0,"data":{"status":2,"url":"https://passport.bilibili.com/account/mobile/security/managephone/phone/verify?tmp_token=2bc5dd260df7158xx860565fxx0d5311&requestId=dffcfxx052fe11xxa9c8e2667739c15c&source=risk","message":"您的账号存在高危异常行为，为了您的账号安全，请验证手机号后登录帐号"}}
        // https://passport.bilibili.com/mobile/verifytel_h5.html
        $response = ApiLogin::passwordLogin($this->username, $this->password, $validate, $challenge);
        //
        $this->loginAfter($mode, $response['code'], $response);
    }

    /**
     * 短信登录
     * @param string $mode
     * @return void
     */
    protected function smsLogin(string $mode = '短信模式'): void
    {
        Log::info("尝试 $mode 登录");
        //
        if (getConf('login_check.phone')) {
            if (!Common::checkPhone($this->username)) {
                failExit('当前用户名不是有效手机号格式');
            }
        }
        //
        $captcha = $this->sendSms($this->username, getConf('login_country.code'));
        $code = $this->cliInput('请输入收到的短信验证码: ');
        $response = ApiLogin::smsLogin($captcha, $code);
        //
        $this->loginAfter($mode, $response['code'], $response);
    }

    /**
     * 扫码登录
     * @param string $mode
     * @return void
     */
    protected function qrcodeLogin(string $mode = '扫码模式'): void
    {
        Log::info("尝试 $mode 登录");
        //
        $this->cliInput("请尝试放大窗口，以确保二维码完整显示，回车继续");
        //
        $response = $this->fetchQrAuthCode();
        $auth_code = $response['auth_code'];
        //
        Qrcode::show($response['url']);
        // max 180 step 3
        foreach (range(0, 180, 3) as $_) {
            sleep(3);
            if ($this->validateQrAuthCode($auth_code)) {
                return;
            }
        }
        failExit("扫码失败 二维码已失效");
    }

    /**
     * 获取AuthCode
     * @return array
     */
    protected function fetchQrAuthCode(): array
    {
        // {"code":0,"message":"0","ttl":1,"data":{"url":"https://passport.bilibili.com/x/passport-tv-login/h5/qrcode/auth?auth_code=xxxx","auth_code":"xxxx"}}
        $response = ApiQrcode::authCode();
        //
        if ($response['code']) {
            failExit('获取AuthCode错误', ['msg' => $response['message']]);
        }
        Log::info("获取到AuthCode: {$response['data']['auth_code']}");
        return $response['data'];
    }

    /**
     * 验证AuthCode
     * @param string $auth_code
     * @return bool
     */
    protected function validateQrAuthCode(string $auth_code): bool
    {
        // {"code":0,"message":"0","ttl":1,"data":{"mid":123,"access_token":"xxx","refresh_token":"xxx","expires_in":2592000}}
        $response = ApiQrcode::poll($auth_code);
        // echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        switch ($response['code']) {
            case 0:
                // 登录成功
                Log::notice("扫码成功 {$response['message']}");
                //
                // $this->updateTvLoginInfo($response);
                $this->updateLoginInfo($response);
                return true;
            case -3:
                // API校验密匙错误
                failExit("扫码失败 {$response['message']}");
            case -400:
                // 请求错误
                failExit("扫码失败 {$response['message']}");
            case 86038:
                // 二维码已失效
                failExit("扫码失败 {$response['message']}");
            case 86039:
                // 二维码尚未确认
                Log::info("等待扫码 {$response['message']}");
                return false;
            default:
                failExit("扫码失败 {$response['message']}");
        }

    }

    /**
     * 登录后处理
     * @param string $mode
     * @param int $code
     * @param array $data
     * @return void
     */
    protected function loginAfter(string $mode, int $code, array $data): void
    {
        switch ($code) {
            case 0:
                // data->data->status number
                if (array_key_exists('status', $data['data'])) {
                    // 二次判断
                    switch ($data['data']['status']) {
                        case 0:
                            // 正常登录
                            $this->loginSuccess($mode, $data);
                            break;
                        case 2:
                            // 异常高危
                            $this->loginFail($mode, $data['data']['message']);
                        case 3:
                            // 需要验证手机号
                            $this->loginFail($mode, "需要验证手机号: {$data['data']['url']}");
                        default:
                            // 未知错误
                            $this->loginFail($mode, '未知错误: ' . json_encode($data));
                    }
                } else {
                    // 正常登录
                    $this->loginSuccess($mode, $data);
                }
                break;
            case -105:
                // 需要验证码
                $this->loginFail($mode, '此次登录需要验证码或' . $data['message']);
            case -629:
                // 密码错误
                $this->loginFail($mode, $data['message']);
            case  -2100:
                // 验证手机号
                $this->loginFail($mode, '账号启用了设备锁或异地登录需验证手机号');
            default:
                // 未知错误
                $this->loginFail($mode, '未知错误: ' . $data['message']);
        }
    }

    /**
     * 登录成功处理
     * @param string $mode
     * @param array $data
     * @return void
     */
    protected function loginSuccess(string $mode, array $data): void
    {
        Log::info("$mode 登录成功");
        $this->updateLoginInfo($data);
        Log::info('生成信息配置完毕');
    }

    /**
     * 登录失败处理
     * @param string $mode
     * @param string $data
     * @return void
     */
    #[NoReturn]
    protected function loginFail(string $mode, string $data): void
    {
        failExit("$mode 登录失败", ['msg' => $data]);
    }

    /**
     * 检查登录
     */
    protected function checkLogin(): void
    {
        $username = getConf('login_account.username');
        $password = getConf('login_account.password');
        if (empty($username) || empty($password)) {
            failExit('空白的帐号和口令');
        }
        $this->username = $username;
        $this->password = $this->publicKeyEnc($password);
    }

    /**
     * 公钥加密
     * @param string $plaintext
     * @return string
     */
    protected function publicKeyEnc(string $plaintext): string
    {
        Log::info('正在载入公钥');
        //
        $response = ApiOauth2::getKey();
        //
        if (isset($response['code']) && $response['code']) {
            failExit('公钥载入失败', ['msg' => $response['message']]);
        } else {
            Log::info('公钥载入完毕');
        }
        //
        $public_key = $response['data']['key'];
        $hash = $response['data']['hash'];
        openssl_public_encrypt($hash . $plaintext, $crypt, $public_key);
        return base64_encode($crypt);
    }

    /**
     * 发送短信验证码
     * @param string $phone
     * @param string $cid
     * @return array
     */
    protected function sendSms(string $phone, string $cid): array
    {
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"4e292933816755442c1568e2043b8e41","recaptcha_url":""}}
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"","recaptcha_url":"https://www.bilibili.com/h5/project-msg-auth/verify?ct=geetest\u0026recaptcha_token=ad520c3a4a3c46e29b1974d85efd2c4b\u0026gee_gt=1c0ea7c7d47d8126dda19ee3431a5f38\u0026gee_challenge=c772673050dce482b9f63ff45b681ceb\u0026hash=ea2850a43cc6b4f1f7b925d601098e5e"}}
        // TODO 参数位置调整
        $payload = [
            'cid' => $cid,
            'tel' => $phone,
            'statistics' => '{"appId":1,"platform":3,"version":"6.86.0","abtest":""}',
        ];

        $raw = ApiLogin::sendSms($payload);
        $response = json_decode($raw, true);
        //
        if ($response['code'] == 0 && isset($response['data']['captcha_key']) && $response['data']['recaptcha_url'] == '') {
            Log::info("短信验证码发送成功 {$response['data']['captcha_key']}");
            $payload['captcha_key'] = $response['data']['captcha_key'];
            return $payload;
        }
        failExit("短信验证码发送失败 $raw");
    }

    /**
     * 输入短信验证码
     * @param string $msg
     * @param int $max_char
     * @return string
     */
    protected function cliInput(string $msg, int $max_char = 100): string
    {
        $stdin = fopen('php://stdin', 'r');
        echo '# ' . $msg;
        $input = fread($stdin, $max_char);
        fclose($stdin);
        return str_replace(PHP_EOL, '', $input);
    }

    /**
     * 获取验证码
     * @return array
     */
    protected function getCaptcha(): array
    {
        $response = ApiCaptcha::combine();
        Log::info('正在获取验证码 ' . $response['code']);
        if ($response['code'] == 0 && isset($response['data']['result'])) {
            return [
                'gt' => $response['data']['result']['gt'],
                'challenge' => $response['data']['result']['challenge'],
                'key' => $response['data']['result']['key'],
            ];
        }
        return [
            'gt' => '',
            'challenge' => '',
            'key' => ''
        ];
    }

    /**
     * 验证码模式
     * @param string $mode
     * @return void
     */
    protected function captchaLogin(string $mode = '验证码模式'): void
    {
//        $captcha_ori = $this->getCaptcha();
//        $captcha = $this->ocrCaptcha($captcha_ori);
//        $this->accountLogin($captcha['validate'], $captcha['challenge'], $mode);
    }

    /**
     * 转换Cookie
     * @param string $token
     * @return string
     */
    protected function token2Cookie(string $token): string
    {
        $response = ApiOauth2::token2Cookie($token);
        $headers = $response['Set-Cookie'];
        $cookies = [];
        foreach ($headers as $header) {
            preg_match_all('/^(.*);/iU', $header, $cookie);
            $cookies[] = $cookie[0][0];
        }
        return implode("", array_reverse($cookies));
    }

}
