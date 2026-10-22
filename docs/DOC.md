# 使用说明

## 环境依赖

通常使用 `composer` 会自动检查以下依赖：

| Requirement |
|-------------|
| PHP >= 8.5.12 |
| ext-openssl |
| ext-json    |
| ext-zlib    |
| ext-mbstring |
| ext-sqlite3 |

### PHP 安全更新

2026-10-21 发布的 PHP 8.5.12 修复了两个可能导致未认证远程代码执行的 PHP 运行时漏洞：

- [CVE-2026-109618：内存耗尽时的请求状态损坏](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
- [CVE-2026-109619：跨请求残留状态导致的释放后使用](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp)

本项目要求 PHP **8.5.12 或更新版本**。主程序使用 CLI，登录助手则通过 `php -S` 或用户配置的 Web 服务器接收 HTTP 请求；默认 Docker Compose 配置开启登录助手并发布 50001 端口。具体利用条件取决于 PHP SAPI、扩展和配置，不能仅因主程序使用 CLI 就认为整个部署不受影响。

必须升级 PHP 运行时并重启所有 PHP 进程。仅更新项目、执行 `composer update`、在请求脚本中增加版本检查或修改 `php.ini` 都不能完整修复这些漏洞，因为部分攻击路径发生在应用代码执行前。完整说明见 [PHP 官方安全公告](https://www.php.net/archive/2026.php#2026-10-21-5)。

源码部署先通过 `php -v` 确认实际使用的 PHP 已更新；单独启动登录助手时，也必须使用更新后的 PHP。

Docker 用户需等待维护者发布包含修复版 PHP 的新镜像，然后拉取并重建容器：

```shell
docker compose pull
docker compose up -d --force-recreate
docker compose exec bhp php -v
```

自行构建时使用 `docker build --pull -f docker/Dockerfile -t bilihelper-personal:local .`，避免复用旧的 PHP 基础镜像。构建时的 Composer 平台检查和容器入口版本检查会拒绝低于 8.5.12 的 PHP；请勿使用 `--ignore-platform-reqs` 绕过检查。已有旧容器必须替换，拉取镜像不会更新正在运行的容器。

## 目录约定

当前运行时以单个 `profile` 为边界，默认使用 `profile/user`。

- `profile/<name>/config`：用户配置、插件开关、可选设备 override
- `profile/<name>/cache`：SQLite 状态库与缓存
- `profile/<name>/log`：日志文件

默认状态库为 `profile/<name>/cache/cache.sqlite3`。

`profile/example` 仅作为模板目录使用，不能直接作为运行 profile。

## 安装与初始化

### 源码运行

1. 克隆项目并复制示例配置：

```shell
git clone https://github.com/lkeme/BiliHelper-personal.git
cp -r profile/example profile/user
```

2. 安装依赖：

```shell
composer install
```

3. 编辑 `profile/user/config/user.ini`

默认只需填写账号密码，再按需开启插件功能。

补充说明：

- `profile/example` 是模板保留目录，不会被命令行直接运行
- 建议复制为 `profile/user` 或其他合法 profile 名称后再启动

### Docker 初始化

当前 Docker 运行时默认不可变。首次生成 profile 需要显式执行：

```shell
entrypoint.sh init_profile
```

或者直接挂载已经准备好的 `profile/user`。

## 设备配置

如需自定义，见 [DEVICE_OVERRIDE.md](./DEVICE_OVERRIDE.md)。

## 命令模式

当前只有三个可执行模式：

```shell
php app.php --help

mode:app     m:a    [主要模式] 默认功能
mode:debug   m:d    [Debug 模式] 开发测试使用
mode:script  m:s    [脚本模式] 使用额外功能脚本
```

补充说明：

- `--help` 与 `mode:script --list` 属于只读命令
- 这两类命令不会初始化 profile，也不会生成 `cache`、`log` 或 `user.ini`

### `mode:app`

默认模式，适合日常运行：

```shell
php app.php
php app.php user
php app.php m:a
php app.php user m:a
php app.php m:a --reset-cache
php app.php m:a --reset-cache --purge-auth
```

- `--reset-cache`：执行前清理当前 `profile` 缓存，默认保留登录态
- `--purge-auth`：与 `--reset-cache` 联用时，同时清理登录态

### `mode:debug`

用于按插件调试：

```shell
php app.php m:d -p VipPoint
php app.php m:d -P VipPoint,DynamicLottery
php app.php m:d -p VipPoint --reset-cache
php app.php m:d -p VipPoint --reset-cache --purge-auth
```

说明：

- `-p / --plugin`：执行单个插件
- `-P / --plugins`：执行多个插件，逗号分隔
- 需要登录的插件会自动补载 `Login`

### `mode:script`

用于执行脚本插件：

```shell
php app.php m:s --help
php app.php m:s --list
php app.php m:s --plugin ActivityInfoUpdate --file urls.txt
php app.php m:s -P BatchUnfollow,ActivityInfoUpdate
php app.php m:s --plugin ActivityInfoUpdate --reset-cache
```

说明：

- `--help`：显示帮助信息, 以及插件内建的参数说明
- `--list`：列出当前脚本插件
- `-p / --plugin`：执行单个脚本插件
- `-P / --plugins`：执行多个脚本插件，逗号分隔
- `ActivityInfoUpdate`：仅支持 `--file` 追加 URL 来源；若不传 `--file`，则仅基于现有 `resources/plugins/ActivityLottery/catalog.json` 刷新
- 同样支持 `--reset-cache` 和 `--purge-auth`

## Docker 使用指南

### 生产环境

生产环境 Docker 运行时默认不可变，容器启动时不会再同步远程代码或刷新依赖。

更新方式：

```shell
docker compose pull
docker compose up -d
```

如需显式初始化 profile：

```shell
docker run --rm IMAGE entrypoint.sh init_profile
```

Docker 默认启动路径支持用环境变量映射现有 CLI 缓存参数：

- `RESET_CACHE=1`：等价于 `--reset-cache`
- `RESET_CACHE=1` 且 `PURGE_AUTH=1`：等价于 `--reset-cache --purge-auth`

例如：

```yaml
environment:
  BRANCH: master
  CAPTCHA: 1
  RESET_CACHE: 1
  PURGE_AUTH: 0
```

说明：

- 该映射只在默认 `entrypoint.sh run` 路径生效
- 如果传入自定义命令，入口脚本不会自动改写命令参数
- `CAPTCHA=1` 时会同时启动登录助手服务，供浏览器完成行为验证码和短信验证码输入
- 登录助手 flow 为一次性流程；如果登录失败、超时、进程退出或容器重启，下次会重新开始完整登录

## 升级指南

### Docker 生产环境

```shell
docker compose pull
docker compose up -d
```

### 源码部署

```shell
cd BiliHelper-personal
git pull
composer install
```

如果使用 `systemd`、`Supervisor` 等进程管理器，请在更新后重启服务。

## 部署指南

如果你将 BiliHelper-personal 部署到线上服务器，需要配置一个进程监控器来拉起：

```shell
php /path/to/your/BiliHelper-personal/app.php m:a
```

通常可以使用：

- `systemd`
- `Supervisor`
- `screen`
- `nohup`

### systemd 示例

```ini
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

### Supervisor 示例

```ini
[program:bilibili]
process_name=%(program_name)s
command=php /path/to/your/BiliHelper-personal/app.php m:a
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/tmp/bilibili.log
```
