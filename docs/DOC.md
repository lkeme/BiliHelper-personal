<p align=center><img width="300px" src="https://user-images.githubusercontent.com/19500576/118621710-36428180-b7f9-11eb-891d-3f5697347cef.png" alt=""></p>

[comment]: <> (<p align=center><img width="300px" src="https://i.loli.net/2018/04/20/5ad97bd395912.jpeg"></p>)

<div align=center> 

[![](https://img.shields.io/badge/Author-Lkeme-blueviolet "作者")](https://github.com/lkeme/ )
![](https://img.shields.io/badge/dynamic/json?label=GitHub%20Followers&query=%24.data.totalSubs&url=https%3A%2F%2Fapi.spencerwoo.com%2Fsubstats%2F%3Fsource%3Dgithub%26queryKey%3Dlkeme&labelColor=282c34&color=181717&logo=github&longCache=true "关注数量")
![](https://img.shields.io/github/stars/lkeme/BiliHelper-personal.svg?style=plastic&logo=appveyor "Star数量")
![](https://img.shields.io/github/forks/lkeme/BiliHelper-personal.svg?style=plastic&logo=stackshare "Fork数量")
![](https://img.shields.io/github/contributors/lkeme/BiliHelper-personal "贡献者")

</div>

## 环境依赖

通常使用 `composer` 工具会自动检测以下依赖问题。

| Requirement  |
|--------------|
| PHP >=8.4    |
| php_openssl  |
| php_json     |
| php_zlib     |
| php_mbstring |
| php_sqlite3  |

## 用户文件夹含义

+ user
    - cache (包含登录状态、以及其他插件缓存)
    - config (个人配置、插件开关、可选 device override)
    - log (日志文件)

## Composer

+ [Composer 安装与使用](https://www.runoob.com/w3cnote/composer-install-and-usage.html)

+ [Composer 下载](https://getcomposer.org/download/)

+ 当前项目 `composer.lock` 基于阿里云 Composer镜像生成

+ 阿里云(全量镜像)

```shell
# 使用帮助
> https://developer.aliyun.com/composer
# 使用命令
> composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

+ 恢复默认镜像|Composer.phar加速下载

```shell
> composer config -g --unset repos.packagist

> https://mirrors.cloud.tencent.com/composer/composer.phar
> https://mirrors.aliyun.com/composer/composer.phar
```

<details>
<summary>其余镜像 展开查看</summary>
<pre><code>

+ cnpkg(全量镜像)

```shell
# 使用帮助
> https://php.cnpkg.org/
# 使用命令
> composer config -g repos.packagist composer https://php.cnpkg.org
```

+ 腾讯云(全量镜像)

```shell
# 使用帮助
> https://mirrors.cloud.tencent.com/help/composer.html
# 使用命令
> composer config -g repos.packagist composer https://mirrors.cloud.tencent.com/composer/
```

+ PhpComposer(全量镜像)

```shell
# 使用帮助
> https://pkg.phpcomposer.com/
# 使用命令
> composer config -g repo.packagist composer https://packagist.phpcomposer.com
```

+ 华为云(全量镜像)

```shell
# 使用帮助
> https://mirrors.huaweicloud.com/repository/php/
# 使用命令
> composer config -g repos.packagist composer https://mirrors.huaweicloud.com/repository/php/
```

+ 交通大学(非全量镜像)

```shell
# 使用帮助
> https://packagist.mirrors.sjtug.sjtu.edu.cn/
# 使用命令
> composer config -g repos.packagist composer https://packagist.mirrors.sjtug.sjtu.edu.cn
```

</code>
</pre>
</details>

## 使用指南

1. 下载（克隆）项目代码，初始化项目

```shell
$ git clone https://github.com/lkeme/BiliHelper-personal.git
$ cp -r profile/example profile/user
```

2. 使用 [composer](https://getcomposer.org/download/) 工具进行安装

```shell
$ composer install
```

3. 按照说明修改配置文件 `profile/user/config/user.ini`

 ```shell
 # 默认只需填写帐号密码，按需求开启其他功能即可
 ...
 ```

其中 `[app].branch` 用于控制远程资源与更新检查使用的分支：

- `master`：默认线上稳定分支
- `dev`：开发测试分支

如果使用 Docker 并传入环境变量 `BRANCH`，则优先使用 `BRANCH`，`app.branch` 作为回退值。

4. 运行命令

```shell
$ php app.php
# 默认即 user + m:a
$ php app.php m:a
$ php app.php user
$ php app.php user m:a
```

> 以下是`多账户多开方案`，单个账户可以无视

5. 复制一份profile/example配置文件夹，修改账号密码即可

 ```shell
 # 默认配置 darling {darling}是配置文件名，可任意
 $ php app.php darling m:a 
 # 如果写了用户参数 不带m:a 与上同等效果 
 $ php app.php darling
 ```

6. 自定义设备方案

默认设备参数位于 `resources/device/default.yaml`

如需自定义，请在个人配置目录使用以下其一：

- `profile/user/config/device.override.yaml`
- `profile/user/config/device.override+.yaml`

7. 命令模式

```shell
# 获取所有命令
$ php app.php
 mode:app     m:a    [主要模式] 默认功能
 mode:debug   m:d    [Debug模式] 开发测试使用
 mode:doctor  m:o    [Doctor模式] 检查 profile 运行条件
 mode:profiles m:p   [Profiles模式] 顺序执行多个 profile
 mode:restore m:r    [复位模式] 复位一些缓存以及设置
 mode:script  m:s    [脚本模式] 使用一些额外功能脚本
```

8. 请保证配置文件存在，否则默认加载`user`配置文件夹

9. 单个或者多个插件测试

```shell
$ php app.php test m:d -p plugin # 单个插件
$ php app.php test m:d -p VipPoint  # 单个插件示例
$ php app.php test m:d -P plugin,plugin1 # 多个插件
$ php app.php test m:d -P VipPoint,Lottery # 多个插件
$ php app.php m:d -p VipPoint --reset-cache # 调试前清理缓存并保留登录态
$ php app.php m:o # 检查当前 profile 与运行时诊断
$ php app.php m:p --profiles user,other # 顺序执行多个 profile
$ php app.php m:r --purge-auth # 清理缓存并同时清理登录态
```

<p align=center><img width="680px" src="https://user-images.githubusercontent.com/19500576/118621472-f8455d80-b7f8-11eb-9fec-500148a566b4.png" alt=""></p>

[comment]: <> (<p align=center><img width="680px" src="https://i.loli.net/2018/04/21/5adb497dc3ece.png"></p>)

## Docker使用指南

<strong>🌐 镜像加速器</strong>（部分网络原因导致拉取镜像失败）<br/><br/>
<details><summary><strong><code> ~</code></strong></summary>

> 以下是通用系统配置，部分特殊系统修改可能不一样，请自行搜索解决。

`在文件 /etc/docker/daemon.json 写入以下内容，如果部分不可用节点自行删除`

```json
{
    "registry-mirrors": [
        "https://docker.1ms.run",
        "https://docker.1panel.live",
        "https://hub.rat.dev"
    ]
}

```

<br>
</details>

1. 安装好[Docker](https://yeasy.gitbooks.io/docker_practice/content/install/)
2. 直接命令行拉取镜像后运行

### 传入的参数方式有两种(二选一，如果同时传入则优先选择配置文件)

- 通过环境变量进行传入

```shell
$ docker run -itd --rm -e USER_NAME=你的B站登录账号 -e USER_PASSWORD=你的B站密码 lkeme/bilihelper-personal
$ docker run -itd --rm -e BRANCH=dev -e USER_NAME=你的B站登录账号 -e USER_PASSWORD=你的B站密码 lkeme/bilihelper-personal
```

- 通过配置文件进行传入(能保留登录状态，自定义配置)

1. 下载 [代码压缩包](https://ghfast.top/https://github.com/lkeme/BiliHelper-personal/archive/refs/heads/master.zip)，解压后提取`profile`目录
2. 将`profile/example`重命名为`profile/user`, 修改 `profile/user/config/user.ini`
3. 通过下面的命令进行挂载并运行

```shell
$ docker run -itd --rm -v /path/to/your/confFilePath:/app/profile/user lkeme/bilihelper-personal
```

- 通过配置文件进行传入且使用验证码(示例命令)

```shell
$ docker run -itd -e MIRRORS=2 -e CAPTCHA=1 -e CAPTCHA_HOST=0.0.0.0 -e CAPTCHA_PORT=50001 -p 50001:50001 -v
 /your/path/bh/profile/user:/app/profile/user lkeme/bilihelper-personal
```

- 当前 Docker 仅支持当前 profile 目录方案

```shell
$ -v /your/path/bh/profile/user:/app/profile/user
```

- 使用github镜像加速

> 以下加速镜像均为网络收集，感谢公益提供者，如有问题请联系删除

```shell
$ -e MIRRORS=0 # 使用 github.com(RAW|源站|默认)
$ -e MIRRORS=1 # 使用 ghfast.top(US|美国)
$ -e MIRRORS=2 # 使用 gitclone.com(CN|中国)
$ -e MIRRORS=3 # 使用 gh-proxy.com(US|美国)
$ -e MIRRORS=4 # 使用 githubfast.com(KR|韩国)
$ -e MIRRORS=5 # 使用 hub.gitmirror.com(US|美国)
$ -e MIRRORS=custom -e CUSTOM_CLONE_URL=https://github.com/lkeme/BiliHelper-personal.git # 使用 自定义克隆地址
```

- 代码分支与资源分支优先级

```shell
$ -e BRANCH=dev     # Docker 代码更新分支，同时也是远程资源分支的最高优先级
```

优先级规则：

- `BRANCH`
- `profile/user/config/user.ini` 中的 `app.branch`
- `master`

- 👉 相关参数

| 选项     | 简单易懂的含义                                            |
|--------|----------------------------------------------------|
| `-i`   | 让你能往容器里输入内容（保持输入通道打开）                              |
| `-t`   | 给容器“配一个键盘和屏幕”，让它像在电脑前运行一样                          |
| `-d`   | 让容器在后台悄悄运行，不占当前窗口                                  |
| `--rm` | 容器一停止，自动删掉它，不留下“垃圾”。（临时测试用，跑完就干净删除，省得手动 docker rm） |
| `-v`   | 把电脑上的文件夹或文件“共享”给容器用（双向同步）                          |

- ✅ 组合小贴士

| 组合     | 含义                   | 必须 |
|--------|----------------------|----|
| `-it`  | 搭配使用，就像“打开一个能打字的控制台” | ✔  |
| `-itd` | 既能输入又有终端，还在后台跑       | ✔  |

- -v模式使用短信登录

配置文件里设置好，等待发送完短信，在命令行中使用命令进入容器，然后输入收到的验证码即可

```shell
$ docker attach 容器ID
$ docker attach --sig-proxy=false 容器ID  # 关闭信号代理：本地终端按键（如 Ctrl+C）不会转发给容器进程，防止误关闭容器
```

```
Q: 容器停止后，容器不见了？
A: 请删除运行时的 --rm 参数

Q: 短信发送后，容器没有等待输入验证码就退出了？
A: 检查docker运行参数是否少了(-it/-itd)参数。

Q: docker attach方式使用ctrl+c退出容器，容器会停止运行怎么办？
A: 请用ctrl+p+q退出容器，容器不会停止运行。

Q: 使用portainer容器管理新建的容器，命令行无法输入直接验证失败怎么办？
A: 在容器设置Advanced container settings -> Commands & logging -> Console -> 勾选 Interactive & TTY (-i -t) ，保存后重新部署容器即可。
```

- 注意: Docker镜像已经包含了所有所需的运行环境，无需在本地环境弄composer。每次启动容器时，都会与项目进行同步以确保版本最新。

- 不需要自动更新

```shell
$ -e AUTO_UPDATE=0 # 不自动更新
```

## 升级指南

> 注意新版本的配置文件是否变动，则需要重新覆盖配置文件，并重新填写设置

1. 进入项目目录

```
$ cd BiliHelper-personal
```

2. 拉取最新代码

```
$ git pull  
```

3. 更新依赖库

```
$ composer install
```

4. 如果使用 systemd 等，需要重启服务

```
$ systemctl restart bilibili
```

## 部署指南

如果你将 BiliHelper-personal 部署到线上服务器时，则需要配置一个进程监控器来监测 `php index.php` 命令，在它意外退出时自动重启。

通常可以使用以下的方式

- systemd (推荐)
- Supervisor
- screen (自用)
- nohup

## systemd 脚本

```
# /usr/lib/systemd/system/bilibili.service

[Unit]
Description=BiliHelper Manager
Documentation=https://github.com/lkeme/BiliHelper-personal
After=network.target

[Service]
ExecStart=/usr/bin/php /path/to/your/BiliHelper-personal/app.php m:a
Restart=always

[Install]
WantedBy=multi-user.target
```

## Supervisor 配置

```
[program:bilibili]
process_name=%(program_name)s
command=php /path/to/your/BiliHelper-personal/app.php m:a
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/tmp/bilibili.log
```

## 报错通知问题

脚本出现 error 级别的报错，会调用通知地址进行提醒，这里推荐两个服务

| 服务          | 官网                                 |
|-------------|------------------------------------|
| Server酱     | https://sc.ftqq.com/               |
| TelegramBot | https://core.telegram.org/bots/api |

示范如下

```
# Server酱
# 自行替换 <SCKEY>
APP_CALLBACK="https://sc.ftqq.com/<SCKEY>.send?text={message}"

# TelegramBot
# 自行替换 <TOKEN> <CHAR_ID>
APP_CALLBACK="https://api.telegram.org/bot<TOKEN>/sendMessage?chat_id=<CHAR_ID>&text={message}"
```

`{message}` 部分会自动替换成错误信息，接口采用 get 方式发送

## 直播间 ID 问题

文件 `user.ini` 里

`ROOM_ID` 配置，填写此项可以清空临过期礼物给指定直播间。

`ROOM_LIST` 配置，使用长位直播间，填写此项可以清空临礼物给指定有勋章的直播间。

`FEED_FILL` 配置，搭配上一条使用，使用过期礼物或者倒序使用正常礼物。

`SOCKET_ROOM_ID` 配置，监控使用，暂时没用到。

通常可以在直播间页面的 url 获取到它

```
https://live.bilibili.com/9522051
```

长位直播间ID获取

```
https://api.live.bilibili.com/room/v1/Room/room_init?id=3
```

所有直播间号码小于 1000 的直播间为短号，部分4位直播间也为短号，

该脚本在每次启动会自动修正部分功能，特殊标注的请留意。
