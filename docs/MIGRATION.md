# 迁移指南

## 目标

当前仓库已经收敛到一套明确的运行模型：

- 显式 `AppKernel + ServiceContainer` 启动链
- 单 `profile` 目录结构
- 单 SQLite 状态库
- 核心 `CorePluginRegistry` + 外部 `ExternalPluginRegistry`
- `mode:app`、`mode:debug`、`mode:script` 三种运行模式

本文件只记录当前已经落地的变化、升级旧环境时需要注意的事项，以及新的运行边界。

## 已完成的迁移

### 1. 启动链改为显式内核装配

当前入口为：

1. `app.php`
2. `Bhp\App\AppKernel`
3. `ServiceContainer`
4. `Bootstrap`
5. `Console`

运行时路径和服务对象统一通过 `ProfileContext` / `AppContext` 获取。

### 2. 旧控制台模式已移除

当前只保留：

- `mode:app`
- `mode:debug`
- `mode:script`

缓存清理改为各模式自带选项：

```bash
php app.php m:a --reset-cache
php app.php m:a --reset-cache --purge-auth
php app.php m:d -p VipPoint --reset-cache
php app.php m:s --plugin ActivityInfoUpdate --reset-cache
```

`mode:doctor`、`mode:profiles`、`mode:restore` 已不再提供。

### 3. `SingleTon` 与运行时路径常量已移除

当前核心模块不再通过 `SingleTon` 装配，也不再依赖 `APP_* / PROFILE_*` 路径常量。

新代码优先使用：

- `ProfileContext`
- `AppContext`
- `Runtime::appContext()`

### 4. 插件运行时改为核心 + 第三方插件注册表

当前插件来源分为两类：

- `src/Plugin/CorePluginRegistry.php` 中的核心插件条目（当前只有 `Login`）
- `plugins/<plugin>/plugin.json` 声明的第三方插件，由 `src/Plugin/ExternalPluginRegistry.php` 发现

当前规则：

- `Plugin` 统一消费核心条目和第三方插件 manifest
- 官方随仓库分发的第三方插件位于 `plugins/*`
- manifest 校验仍保留，用于筛掉不兼容插件
- `mode:script` 只装配脚本插件；`mode:app` 和 `mode:debug` 装配非脚本插件

### 5. Docker 生产运行时改为默认不可变

当前生产容器启动时不会执行远程代码同步。更新方式固定为：

```bash
docker compose pull
docker compose up -d
```

### 6. ActivityLottery 改为行级 SQLite 持久化

`ActivityFlowStore` 当前将活动流写入 `profile/<name>/cache/cache.sqlite3` 中的 `activity_flow_entries` 表。

当前行为：

- 每条记录按 `scope + biz_date + flow_id` 唯一键存储
- 同一日内多个 flow 可以独立 upsert
- 不再使用“按天一个大对象”的持久化模型

## 旧 profile 升级说明

### 自动处理

升级旧 profile 到当前版本时，程序会继续自动处理：

- 老缓存文件导入 SQLite
- 老任务状态文件导入新版状态库
- 旧 `device/` 目录清理
- 缺失配置按模板补齐

### 手动清理缓存

如果只想清理缓存并保留登录态，使用：

```bash
php app.php m:a --reset-cache
```

如果需要同时清理登录态，使用：

```bash
php app.php m:a --reset-cache --purge-auth
```

### 调试与脚本模式

调试或脚本执行前也支持相同的缓存清理选项：

```bash
php app.php m:d -p VipPoint --reset-cache
php app.php m:d -P VipPoint,Lottery --reset-cache --purge-auth
php app.php m:s --plugin ActivityInfoUpdate --reset-cache
```

## 新代码约束

新增代码时，至少满足以下规则：

- 不再新增 `SingleTon`、运行时路径常量或新的全局 helper
- 业务插件必须来自 `plugins/<plugin>/plugin.json`，核心只保留系统级插件
- 优先通过 `AppContext` 访问配置、设备、认证态和路径
- 运行态状态优先进入 SQLite 或独立状态仓
- 文档必须描述当前真实行为，不保留已经失效的控制台模式和部署方式
