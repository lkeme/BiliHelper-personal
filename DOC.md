
<p align="center"><img width="300px" src="https://i.loli.net/2018/04/20/5ad97bd395912.jpeg"></p>

<p align="center">
<img src="https://img.shields.io/badge/version-0.6.5.200808 alpha-green.svg?longCache=true&style=for-the-badge">
<img src="https://img.shields.io/badge/license-mit-blue.svg?longCache=true&style=for-the-badge">
</p>


# BiliHelper

B 站直播实用脚本

## 功能组件

|plugin              |version             |description         |
|--------------------|--------------------|--------------------|
|Login               |20.08.08            |账号登录            |
|Schedule            |20.08.08            |休眠控制            |
|MasterSite          |20.08.08            |主站助手            |
|Daily               |20.08.08            |每日礼包            |
|Heart               |20.08.08            |双端心跳            |
|Task                |20.08.08            |每日任务            |
|Silver              |20.08.08            |银瓜子宝箱          |
|Barrage             |20.08.08            |活跃弹幕            |
|Silver2Coin         |20.08.08            |银瓜子换硬币        |
|GiftSend            |20.08.08            |礼物赠送            |
|Judge               |20.08.08            |风纪                |
|GroupSignIn         |20.08.08            |友爱社签到          |
|ManGa               |20.08.08            |漫画签到分享        |
|Match               |20.08.08            |赛事签到分享        |
|GiftHeart           |20.08.08            |心跳礼物            |          
|MaterialObject      |20.08.08            |实物抽奖            |
|AloneTcpClient      |20.08.08            |独立监控            |
|ZoneTcpClient       |20.08.08            |分区监控            |
|StormRaffle         |20.08.08            |节奏风暴            |
|GiftRaffle          |20.08.08            |活动礼物            |
|PkRaffle            |20.08.08            |大乱斗              |
|GuardRaffle         |20.08.08            |舰长总督            |
|AnchorRaffle        |20.08.08            |天选时刻            |
|AwardRecord         |20.08.08            |获奖通知            |
|Statistics          |20.08.08            |数据统计            |
|Competition         |20.08.08            |赛事竞猜            |
|SmallHeart          |20.08.08            |小心心              |
|ActivityLottery     |20.08.08            |主站活动            |
     
## 打赏赞助

![](https://i.loli.net/2019/07/13/5d2963e5cc1eb22973.png)

> 待添加

## 未完成功能

|待续       |
|-----------|
|多用户     |

## 环境依赖

|Requirement         |
|--------------------|
|PHP >=7.0           |
|php_curl            |
|php_sockets         |
|php_openssl         |
|php_json            |
|php_zlib            |
|php_mbstring        |

通常使用 `composer` 工具会自动检测上述依赖问题。  

## Composer
* 项目 `composer.lock` 基于阿里云Composer镜像生成
+ 阿里云(推荐)
```
# 使用帮助
https://developer.aliyun.com/composer
# 使用命令
> composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```
* 腾讯云(备用)
```
# 使用帮助
https://mirrors.cloud.tencent.com/composer/
# 使用命令
composer config -g repos.packagist composer https://mirrors.cloud.tencent.com/composer/
```



## 使用指南

 1. 下载（克隆）项目代码，初始化项目
```
$ git clone https://github.com/lkeme/BiliHelper-personal.git
$ cd BiliHelper-personal/conf
$ cp user.conf.example user.conf
```
 2. 使用 [composer](https://getcomposer.org/download/) 工具进行安装
```
$ composer install
```
 3. 按照说明修改配置文件 `user.conf`
 ```
 # 默认只需填写帐号密码，按需求开启其他功能即可
 ```
 4. 运行测试
```
$ php index.php
```
> 以下是`多账户多开方案`，单个账户可以无视
 5. 复制一份example配置文件，修改账号密码即可
 ```
 $ php index.php example.conf
 ```
 6. 请保证配置文件存在，否则默认加载`user.conf`配置文件

<p align="center"><img width="680px" src="https://i.loli.net/2018/04/21/5adb497dc3ece.png"></p>

## Docker使用指南

  1. 安装好[Docker](https://yeasy.gitbooks.io/docker_practice/content/install/)
  2. 直接命令行拉取镜像后运行


### 传入的参数方式有两种(二选一，如果同时传入则优先选择配置文件)

- 通过环境变量进行传入

```shell script
  docker run -itd --rm -e USER_NAME=你的B站登录账号 -e USER_PASSWORD=你的B站密码 zsnmwy/bilihelper-personal
```

- 通过配置文件进行传入

1. 下载[配置文件](https://raw.githubusercontent.com/lkeme/BiliHelper-personal/master/conf/user.conf.example)
2. 修改
3. 通过下面的命令进行挂载并运行

```shell script
docker run -itd --rm -v /path/to/your/confFileName.conf:/app/conf/user.conf zsnmwy/bilihelper-personal
```

```
相关参数

  -it 前台运行
  -itd 后台运行
  -v 本地文件:容器内部文件 ==> 挂载本地文件到容器中。本地文件路径随便变，容器内部文件路径不能变。
  ```

- 注意: Docker镜像已经包含了所有所需的运行环境，无需在本地环境弄composer。每次启动容器时，都会与项目进行同步以确保版本最新。

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
Description=Bili Helper Manager
Documentation=https://github.com/lkeme/BiliHelper-personal
After=network.target

[Service]
ExecStart=/usr/bin/php /path/to/your/BiliHelper-personal/index.php
Restart=always

[Install]
WantedBy=multi-user.target
```

## Supervisor 配置

```
[program:bilibili]
process_name=%(program_name)s
command=php /path/to/your/BiliHelper-personal/index.php
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/tmp/bilibili.log
```

## 报错通知问题

脚本出现 error 级别的报错，会调用通知地址进行提醒，这里推荐两个服务

|服务|官网|
|---|---|
|Server酱|https://sc.ftqq.com/|
|TelegramBot|https://core.telegram.org/bots/api|

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

文件 `user.conf` 里

`ROOM_ID` 配置，填写此项可以清空临过期礼物给指定直播间。

`ROOM_LIST` 配置，使用长位直播间，填写此项可以清空临礼物给指定有勋章的直播间。

`FEED_FILL` 配置，搭配上一条使用，使用过期礼物或者倒序使用正常礼物。

`SOCKET_ROOM_ID` 配置，监控使用，暂时没用到。

通常可以在直播间页面的 url 获取到它
```
http://live.bilibili.com/9522051
```

长位直播间ID获取
```
https://api.live.bilibili.com/room/v1/Room/room_init?id=3
```

所有直播间号码小于 1000 的直播间为短号，部分4位直播间也为短号，

该脚本在每次启动会自动修正部分功能，特殊标注的请留意。

## 相关

 >  [BilibiliHelper](https://github.com/metowolf/BilibiliHelper) 
 
 > [BiliHelper](https://github.com/lkeme/BiliHelper)
 
 >  [Github](https://github.com/)


## License 许可证

BiliHelper is under the MIT license.

本项目基于 MIT 协议发布，并增加了 SATA 协议。

当你使用了使用 SATA 的开源软件或文档的时候，在遵守基础许可证的前提下，你必须马不停蹄地给你所使用的开源项目 “点赞” ，比如在 GitHub 上 star，然后你必须感谢这个帮助了你的开源项目的作者，作者信息可以在许可证头部的版权声明部分找到。

本项目的所有代码文件、配置项，除另有说明外，均基于上述介绍的协议发布，具体请看分支下的 LICENSE。

此处的文字仅用于说明，条款以 LICENSE 文件中的内容为准。
