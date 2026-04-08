# 插件 Manifest 链接元数据设计

## 目标

为插件 manifest 新增两个统一元数据字段：

- `activity_url`
- `reference_links`

这两个字段只作为开发参考补充信息，不参与插件运行、调度、配置生成、自检决策和用户侧展示。

## 字段定义

每个插件 manifest 必须显式包含：

```json
{
  "activity_url": "",
  "reference_links": []
}
```

其中：

- `activity_url` 是单个字符串
- `reference_links` 是对象列表

`reference_links` 的单项结构固定为：

```json
{
  "url": "https://example.com/*.html",
  "comment": "活动页入口"
}
```

## 空值语义

统一约定：

- `activity_url` 无值时写 `""`
- `reference_links` 无值时写 `[]`

不使用：

- `null`
- 省略字段
- 其他空值表达方式

## 校验策略

校验目标是“结构统一”，不是“严格链接合法性检查”。

### 必须校验

- `activity_url` 字段存在且为字符串
- `reference_links` 字段存在且为数组
- `reference_links` 每项存在 `url` 和 `comment`
- `reference_links[*].url` 和 `reference_links[*].comment` 都必须为字符串
- `reference_links[*].url` 不允许为空字符串

### 明确允许

- `activity_url = ""`
- `reference_links = []`
- `reference_links[*].comment = ""`
- 类似 `https://xxx.xxx/*.html` 的宽松 URL 形式

### 明确不做

- 严格 RFC URL 校验
- 联网探测链接有效性
- 运行时自动修正或自动去重

## 适用范围

本次统一补齐：

- 所有 `plugins/*/plugin.json`
- `Login` 内置 manifest
- 插件模板

本次不做真实链接内容填充，统一先补默认空值，后续由人工逐步填写。

## 代码改动范围

### 需要改动

- `src/Plugin/PluginManifest.php`
- `src/Plugin/PluginManifestValidator.php`
- `src/Plugin/CorePluginRegistry.php`
- 所有现有 `plugins/*/plugin.json`
- 插件模板
- 一段简短开发说明

### 不需要改动

- 调度逻辑
- 插件运行逻辑
- 用户配置逻辑
- 控制台展示逻辑
- registry 顶层展示字段

## 推荐实施顺序

1. 扩展 `PluginManifest`
2. 扩展 `PluginManifestValidator`
3. 补齐 `Login` 内置 manifest
4. 批量补齐所有现有插件 manifest
5. 更新插件模板
6. 补一段开发说明
7. 执行 manifest 解析与装配回归验证

## 示例

```json
{
  "activity_url": "",
  "reference_links": [
    {
      "url": "https://api.live.bilibili.com/xxx",
      "comment": "活动接口"
    },
    {
      "url": "https://www.bilibili.com/blackboard/*.html",
      "comment": "活动页入口"
    }
  ]
}
```
