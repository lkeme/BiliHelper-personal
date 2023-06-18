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

use Bhp\Api\Passport\ApiCaptcha;
use Bhp\Api\Passport\ApiLogin;
use Bhp\Api\Passport\ApiOauth2;
use Bhp\Api\PassportTv\ApiQrcode;
use Bhp\Api\WWW\ApiMain;
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
        $mode_id = (int)getConf('login_mode.mode');
        $this->checkLogin($mode_id);
        //
        switch ($mode_id) {
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
     * cookie补丁
     * @return string
     */
    public function patchCookie(): string
    {
//        $response = ApiMain::home();
        $bvid_list = ["BV16X4y1g7wT", "BV1cy4y1k7A2", "BV1bz4y1r7Ug", "BV1ti4y1K7uw", "BV1GK411K7Ke", "BV1CC4y1a7ee", "BV1PK411L7h5", "BV1qt411j7fV", "BV1yt4y1Q7SS", "BV1mi4y1b76M", "BV1pi4y147tQ", "BV1FE411A7Xd", "BV19E41197Kc", "BV1tJ411W7hw", "BV1w7411P7jJ", "BV1Jb411W7dH", "BV12J411X7cD", "BV1Nt4y1D7pW", "BV1Wb411v7WN", "BV1Yc411h7uQ", "BV1x54y1e7zf", "BV1UE411y7Wy", "BV1zp4y1U7Z5", "BV1mK411V7wY", "BV1ht411L72V", "BV16Z4y1H7NG", "BV1jE41137eu", "BV1dW411n7La", "BV1Jb411U7u2", "BV1kt411d7Ht", "BV1Sx411T7QQ", "BV1bW411n7fY", "BV1Ys41167aL", "BV1es411D7sW", "BV1f4411M7QC", "BV1XW411F7L6", "BV1xx411c7mu", "BV1Ss411o7vY", "BV1js411f7jY", "BV1gs411B7y4", "BV12s411N7g2", "BV1fs411t7EK", "BV15W411W7NJ", "BV1xx411c7XW", "BV1vx411K7jb", "BV1Ls41127sG", "BV1GW411g7mc", "BV1Hx411V7n9", "BV1hs411Q7zf", "BV1zs411S7sz", "BV1Us411d71V", "BV1EW41167Yv", "BV1px411N7Yd", "BV1Yx411A7wM", "BV1Js411o76u", "BV1Xs411X7wh", "BV1nx411F7Jf", "BV1Dt411r7Tv", "BV1xx411c79H", "BV1Bx411c7hB", "BV1ix411c7Ye", "BV1Vs411y7TM", "BV1rs411S736", "BV11p411o73u", "BV1Js411Z7Nq", "BV1nx411F7fM", "BV1YW411n7aW", "BV1Ds411m7c5", "BV1Fx411w7GK", "BV1cs411S7DX", "BV1cb411V7Lm", "BV1Kt41147o3", "BV1Mt411D73n", "BV1fx411c7v6", "BV1dx411P79c", "BV1es41197ai", "BV1hx411w7MG", "BV1Ys411H7QK", "BV1Kx411y7TJ", "BV1ts411D7mf", "BV1Sx41117dD", "BV1tx411P7N4", "BV1fs411k7Kj", "BV1Sx411T7L3", "BV1es41197hA"];
        $response = ApiMain::video($bvid_list[array_rand($bvid_list)]);
        $headers = $response['Set-Cookie'];
        $cookies = [];
        foreach ($headers as $header) {
            preg_match_all('/^(.*);/iU', $header, $cookie);
            $cookies[] = $cookie[0][0];
        }
        return implode("", array_reverse($cookies));
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
        // patch cookie
        $this->updateInfo('pc_cookie', $cookie . $this->patchCookie());
        //
        $user = User::parseCookie();
        $this->updateInfo('uid', $user['uid'], false);
        $this->updateInfo('csrf', $user['csrf'], false);
        $this->updateInfo('sid', $user['sid'], false);
        //
        // $this->updateInfo('username',$this->username);
        // $this->updateInfo('password',$this->password);
        // 转换
        $access_token = $this->tvConvert();
        $this->updateInfo('access_token', $access_token);
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
     * @param string $qr
     * @return void
     */
    protected function qrcodeLoginShow(string $qr): void
    {
        Log::info("1.终端直接显示(输入:1)");
        Log::info("2.浏览器链接访问(输入:2)");
        $option = $this->cliInput("请输入二维码显示方式: ");
        switch ($option) {
            case '1':
                //
                $this->cliInput("请尝试放大窗口，以确保二维码完整显示，回车继续");
                Qrcode::show($qr);
                break;
            case '2':
                Log::info("请使用浏览器访问下面的链接，以确保二维码完整显示");
//                $url = 'https://cli.im/api/qrcode/code?text=' . urlencode($qr) . '&mhid=';
                $url = 'https://cli.im/api/qrcode/code?text=' . urlencode($qr);
                Log::info($url);
                break;
            default:
                failExit('无效的选项');
        }
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
        $response = $this->fetchQrAuthCode();
        $auth_code = $response['auth_code'];
        //
        $this->qrcodeLoginShow($response['url']);
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
     * 验证码登录
     * @param string $target_url
     * @return void
     */
    protected function captchaLogin(string $target_url): void
    {
//        $captcha_ori = $this->getCaptcha();
//        $captcha = $this->ocrCaptcha($captcha_ori);
        $captcha_info = $this->matchCaptcha($target_url);
        // 暂时不做额外处理
        $captcha = $this->ocrCaptcha($captcha_info['gt'], $captcha_info['challenge']);
        $this->accountLogin($captcha['validate'], $captcha['challenge'], $mode = '账密模式(行为验证码)');
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
                Log::warning("此次请求需要行为验证码");
                $this->captchaLogin($data['data']['url']);
                break;
//                $this->loginFail($mode, '此次登录需要验证码或' . $data['message']);
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
    protected function checkLogin(int $mode_id): void
    {
        $username = getConf('login_account.username');
        $password = getConf('login_account.password');

        // TODO 冗余
        switch ($mode_id) {
            case 1:
                if (empty($username) || empty($password)) {
                    failExit('空白的帐号和口令');
                }
                break;
            case 2:
                if (empty($username)) {
                    failExit('空白的帐号');
                }
                break;
            default:
                // 3 4
                break;
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
     * @param string $validate
     * @param string $challenge
     * @param string $recaptcha_token
     * @return array
     */
    protected function sendSms(string $phone, string $cid, string $validate = '', string $challenge = '', string $recaptcha_token = ''): array
    {
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"","recaptcha_url":"https://www.bilibili.com/h5/project-msg-auth/verify?ct=geetest\u0026recaptcha_token=f968b6432dde47a9aa274adfc60b2d1a\u0026gee_gt=1c0ea7c7d47d8126dda19ee3431a5f38\u0026gee_challenge=dec6522102ce0aa5cbdab370930123f8\u0026hash=ef1e5849a6746ad680a1dfa8924da497"}}
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"4e292933816755442c1568e2043b8e41","recaptcha_url":""}}
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"","recaptcha_url":"https://www.bilibili.com/h5/project-msg-auth/verify?ct=geetest\u0026recaptcha_token=ad520c3a4a3c46e29b1974d85efd2c4b\u0026gee_gt=1c0ea7c7d47d8126dda19ee3431a5f38\u0026gee_challenge=c772673050dce482b9f63ff45b681ceb\u0026hash=ea2850a43cc6b4f1f7b925d601098e5e"}}
        // TODO 参数位置调整
        $payload = [
            'cid' => $cid,
            'tel' => $phone,
            'statistics' => getDevice('app.bili_a.statistics'),
        ];
        if ($validate != '' && $challenge != '') {
            $payload['recaptcha_token'] = $recaptcha_token;
            $payload['gee_validate'] = $validate;
            $payload['gee_challenge'] = $challenge;
            $payload['gee_seccode'] = "$validate|jordan";
        }
        $raw = ApiLogin::sendSms($payload);
        $response = json_decode($raw, true);
        //
        if ($response['code'] == 0 && isset($response['data']['captcha_key']) && $response['data']['recaptcha_url'] == '') {
            Log::info("短信验证码发送成功 {$response['data']['captcha_key']}");
            $payload['captcha_key'] = $response['data']['captcha_key'];
            return $payload;
        }
        if ($response['code'] == 0 && isset($response['data']['recaptcha_url']) && $response['data']['recaptcha_url'] != '') {
            Log::warning("此次请求需要行为验证码");
            $target_url = $response['data']['recaptcha_url'];
            // 单独处理
            preg_match('/recaptcha_token=([a-f0-9]+)/', $target_url, $matches);
            $recaptcha_token = $matches[1];

            $captcha_info = $this->matchCaptcha($target_url);
            // 暂时不做额外处理
            $captcha = $this->ocrCaptcha($captcha_info['gt'], $captcha_info['challenge']);
            return $this->sendSms($phone, $cid, $captcha['validate'], $captcha['challenge'], $recaptcha_token);
        }


        failExit("短信验证码发送失败 $raw");
    }

    /**
     * @param string $target_url
     * @return array
     */
    protected function matchCaptcha(string $target_url): array
    {
        preg_match('/gt=([a-f0-9]+)/', $target_url, $matches);
        $gt = $matches[1];
        preg_match('/challenge=([a-f0-9]+)/', $target_url, $matches);
        $challenge = $matches[1];
        if (empty($gt) || empty($challenge)) {
            failExit('提取验证码失败');
        }
        return ['gt' => $gt, 'challenge' => $challenge];
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
     * @param string $gt
     * @param string $challenge
     * @return array
     */
    protected function ocrCaptcha(string $gt, string $challenge): array
    {
        if (getConf('login_captcha.url') && getEnable('login_captcha')) {
            Log::info('请在浏览器中打开以下链接，完成验证码识别');
            Log::info(getConf('login_captcha.url') . '/geetest?gt=' . $gt . '&challenge=' . $challenge);
            Log::info('请在2分钟内完成识别操作');
            // 设置请求时间和时间间隔
            $maxTime = 120; // 最大请求时间（秒）
            $interval = 2; // 请求间隔（秒）

            // 循环请求
            $startTime = time();
            while (time() - $startTime < $maxTime) {
                $response = ApiCaptcha::fetch($challenge);
                if ($response['code'] == 10000) {
                    Log::notice($response['message']);
                    return $response['data'];
                } else {
                    Log::info($response['message']);
                }
                sleep($interval);
            }
            failExit('验证码识别超时');
        } else {
            failExit('验证码识别并未开启');
        }
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


    /**
     * @return string
     */
    protected function tvConvert(): string
    {
        # Android 旧
        $app_key = base64_decode(getDevice('app.bili_a.app_key'));
        $app_secret = base64_decode(getDevice('app.bili_a.secret_key'));

        $response = ApiQrcode::getConfrimUrl($app_key, $app_secret);
        if ($response['code'] == 0 && isset($response['data']['has_login']) && $response['data']['has_login'] == 1) {
            Log::info('获取tv转换确认链接成功');
        } else {
            failExit('获取转换确认链接失败');
        }
        //
        $next_url = $response['data']['confirm_uri'];
        $response = ApiQrcode::goConfrimUrl($next_url);
        $location = $response['Location'][0];
//        var_dump($location);
        preg_match('/access_key=([a-f0-9]+)/', $location, $matches);
        $access_key = $matches[1];
//        var_dump($matches);
        if (empty($access_key)) {
            failExit('获取转换access_key失败');
        }
        Log::info('获取tv转换access_key成功');
        return $access_key;
    }

}
