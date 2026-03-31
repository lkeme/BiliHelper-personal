# 迁移指南

## 目标

当前仓库已经从“多套运行模型并存”的旧状态，收敛到“单套 profile、单套缓存、单套 HTTP、单套插件执行模型”。

本文件只记录当前已经落地的变化、仍保留的边界，以及升级旧 profile 时需要注意的事项。

## 已完成的迁移

### 1. helper 桥接已完全移除

以下全局 helper 已删除：

- `getConf`
- `setConf`
- `getEnable`
- `getU`
- `setU`
- `getDevice`
- `getAppName`
- `getAppVersion`
- `getAppHomePage`
- `failExit`
- `requireDir`

新代码统一改为显式调用：

- `Runtime::getInstance()->context()`
- `Config::getInstance()`
- `Device::getInstance()`
- `AppTerminator::fail()`
- `DirectoryLoader::requireDir()`

### 2. 插件执行模型已统一

业务插件统一迁移到：

- `PluginTaskInterface`
- `runOnce(): TaskResult`

以下旧模型已移除：

- `execute() + TimeLock`
- `Schedule`
- `Task`
- `TaskQueue`

### 3. 登录已内置

登录能力已经移动到：

- `src/Login/*`

旧目录已移除：

- `plugin/Login/Login.php`

二维码登录模式不再支持，当前只保留：

- 账密登录
- 短信登录

### 4. 认证态已改为直接持久化

当前登录态直接按 profile 存入 SQLite 状态库，不再依赖密钥文件。

已移除：

- `secrets/auth.key`
- `BHP_AUTH_SECRET`
- `BHP_AUTH_SECRET_FILE`

### 5. 缓存已统一到 SQLite

当前默认状态库：

- `profile/<name>/cache/cache.sqlite3`

兼容行为：

- 老版本 `*.dat` / `*.dat.gz` 缓存会在首次访问对应 scope 时导入 SQLite
- 导入成功后会自动清理旧文件

### 6. 设备参数已改为全局默认 + 可选 override

当前默认设备文件：

- `resources/device/default.yaml`

可选自定义：

- `profile/<name>/config/device.override.yaml`
- `profile/<name>/config/device.override+.yaml`

旧路径：

- `profile/<name>/device/device.yaml`

已不再读取；若旧 profile 中还残留该目录，升级后可直接删除。

### 7. 插件别名兼容层已移除

项目内已不再依赖 `class_alias()` 兼容历史插件类名。

当前插件发现流程为：

1. 扫描插件目录
2. 解析候选入口文件
3. 解析类名
4. 校验 `PluginManifest`
5. 装配插件实例

### 8. HTTP 仅保留单轨实现

当前仅使用：

- `amphp/http-client`

已移除：

- Guzzle 双实现
- `MultiRequest`
- OpenTelemetry tracing 接入

## 旧 profile 升级说明

### 自动迁移

升级旧 profile 到当前版本时，程序会自动处理：

- 老缓存文件导入 SQLite
- 老任务状态文件导入新版状态库
- 老 `device/` 目录清理
- 旧配置缺失字段按模板补齐

### 手动清理

如果只是想清理缓存而保留登录态，使用：

```bash
php app.php m:r
```

如果需要连登录态一起清理，使用：

```bash
php app.php m:r --purge-auth
```

### 调试模式快捷清理

调试单个或多个插件时，可在进入前清缓存并保留登录态：

```bash
php app.php m:d -p VipPoint --reset-cache
php app.php m:d -P VipPoint,Lottery --reset-cache
```

## 当前仍保留的边界

### 1. SingleTon 装配仍然存在

以下核心模块仍以 `SingleTon` 装配：

- `Bootstrap`
- `Core`
- `Console`
- `Runtime`
- `Plugin`
- `Scheduler`
- `Request`
- `Log`
- `Notice`

这部分目前不是兼容壳，而是现阶段主装配方式。新代码仍应优先减少继续扩散。

### 2. 运行时常量仍然存在

当前仍由 `Core` 初始化以下路径常量：

- `APP_RESOURCES_PATH`
- `APP_PLUGIN_PATH`
- `PROFILE_CONFIG_PATH`
- `PROFILE_LOG_PATH`
- `PROFILE_CACHE_PATH`

新代码优先使用：

- `ProfileContext`
- `AppContext`

### 3. API 数组返回仍较多

虽然部分热点链路已经开始引入 DTO 和状态仓，但 `src/Api/*` 仍以静态数组返回为主。这是当前剩余的主要结构性演进点之一。

## 新代码约束

新增代码时，至少满足以下规则：

- 不再新增全局 helper
- 不再新增双轨实现或兼容壳
- 优先通过 `AppContext` 访问配置、设备、认证和路径
- 插件统一返回 `TaskResult`
- 运行态状态优先进入 SQLite 或独立状态仓
- 文档应描述当前真实行为，不再保留已删除系统的说明
