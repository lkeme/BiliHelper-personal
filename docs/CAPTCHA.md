## 关于验证码

> 在登录模块中，账密模式或短信验证码模式可能会遇到验证码，这里将会介绍如何解决验证码问题。

### 开关

```ini
[login_captcha]
; 验证码手动识别
enable = false
; 验证码手动识别服务地址
url = "http://localhost:50001"
```

### 如何开启验证码手动识别服务地址

> 需要手动开启配置开关`enable = true`，并且配置`url`为验证码手动识别服务地址。

#### Docker用户

以外部挂载配置文件为例

```bash
docker run -itd --rm -e CAPTCHA=1 -e CAPTCHA_HOST=localhost -e CAPTCHA_PORT=50002 -p 50002:50002 -v /path/to/your/confFilePath:/app/profile/user lkeme/bilihelper-personal

-e CAPTCHA=1 # 开启验证码手动识别服务  默认 0
-e CAPTCHA_HOST=localhost # 默认 0.0.0.0
-e CAPTCHA_PORT=50002 # 验证码手动识别服务地址  默认 50001 需要注意端口映射关系
```

> 注意：如果你使用的是`docker-compose`，请参考`docker-compose.yml`文件中的`captcha`服务配置。

##### 本地用户

```bash
cd captcha && php -S localhost:50001
cd captcha && php -S localhost:50002
```

> 验证码处理目录在`captcha`目录下，可以自行修改。

### 如何使用验证码手动识别服务地址

在开启验证码手动识别服务地址后，登录模块会自动显示手动地址。复制地址到浏览器中打开，会显示相应的界面。

识别后提交反馈，程序会自动进行获取。

> 0.0.0.0是指所有地址，本地以及外网都可以访问，如果你只想本地访问，可以使用`localhost`或者其他内网地址。

> 注意：`该部分没做任何安全上的处理，不要长时间暴露于在公网上。开在外网，请使用时再打开，不使用时最好关闭或host至于内网中`。
