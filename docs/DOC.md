# 使用说明

## 环境依赖

通常使用 `composer` 会自动检查以下依赖：

| Requirement |
|-------------|
| PHP >= 8.5  |
| ext-openssl |
| ext-json    |
| ext-zlib    |
| ext-mbstring |
| ext-sqlite3 |

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

默认设备参数位于 `resources/device/default.yaml`。

如需自定义，请在个人配置目录使用以下其一：

- `profile/<name>/config/device.override.yaml`
- `profile/<name>/config/device.override+.yaml`

旧的 `profile/<name>/device/device.yaml` 已不再读取。

## 启动链与插件来源

当前入口为 `app.php`，启动链为：

1. `AppKernel`
2. `ServiceContainer`
3. `Bootstrap`
4. `Console`

核心只保留 `Login`，其余业务插件统一从 `plugins/<plugin>/plugin.json` 发现。
项目自带插件位于 `plugins/*`，属于 bundled third-party plugins。

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
php app.php m:s --list
php app.php m:s --plugin ActivityInfoUpdate
php app.php m:s -P BatchUnfollow,ActivityInfoUpdate
php app.php m:s --plugin ActivityInfoUpdate --reset-cache
```

说明：

- `--list`：列出当前脚本插件
- `-p / --plugin`：执行单个脚本插件
- `-P / --plugins`：执行多个脚本插件，逗号分隔
- 同样支持 `--reset-cache` 和 `--purge-auth`

## 配置补充

示例配置 `profile/example/config/user.ini` 当前额外包含请求治理区段：

```ini
[request_governance]
enable = false
mode = observe
window_seconds = 60
max_requests_per_host = 60
cooldown_seconds = 30
```

说明：

- `mode` 仅支持 `observe` 与 `enforce`
- 关闭时保持观测关闭，不会触发限流拦截

## ActivityLottery 持久化

`ActivityLottery` 当前使用 `ActivityFlowStore` 将活动流写入 `cache.sqlite3` 的行级记录中。

当前特点：

- 每个 flow 按 `scope + biz_date + flow_id` 存储
- 同一天内多个 flow 独立 upsert
- 不再使用按天聚合的大对象缓存

## Docker 使用指南

### 生产环境

生产 Docker 运行时默认不可变，容器启动时不会再同步远程代码或刷新依赖。

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
