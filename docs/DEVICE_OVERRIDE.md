# Device Override

## 概述

默认设备参数位于 `resources/device/default.yaml`。

如需为某个 profile 自定义设备参数，请在对应目录下创建以下文件之一：

- `profile/<name>/resources/device/device.override.yaml`
- `profile/<name>/resources/device/device.override+.yaml`

两者不要同时使用。程序会优先读取 `device.override.yaml`，否则再读取 `device.override+.yaml`。

## 区别

### `device.override.yaml`

这是完整替换模式。

- 程序不再使用 `resources/device/default.yaml` 的内容
- 最终设备配置完全来自 `profile/<name>/resources/device/device.override.yaml`
- 适合你想自己维护一整份完整设备配置时使用
- 适合大量修改字段时使用

### `device.override+.yaml`

这是递归合并模式。

- 程序先读取 `resources/device/default.yaml`
- 再用 `profile/<name>/resources/device/device.override+.yaml` 覆盖同名字段
- 未写出的字段继续沿用默认值
- 适合只改少数字段时使用

## 示例

默认文件节选：

```yaml
app:
  bili_a:
    version: "8.90.2"
    build: "8902100"
    channel: "bili"
platform:
  system:
    model: "MuMu"
    network: "2"
```

### 示例 1：完整替换

文件路径：

`profile/user/resources/device/device.override.yaml`

操作方式：

1. 先完整复制 `resources/device/default.yaml`
2. 保存为 `profile/user/resources/device/device.override.yaml`
3. 只修改已有字段的值，不要新增字段，也不要删除字段

说明：

- 实际文件必须是从 `resources/device/default.yaml` 复制出来的完整版本
- 字段不能增删，只能修改现有字段的值

### 示例 2：递归合并

文件路径：

`profile/user/resources/device/device.override+.yaml`

文件内容：

```yaml
app:
  bili_a:
    version: "8.80.0"
    build: "8800000"

platform:
  system:
    model: "Pixel 7"
    os_ver: "9"
```

最终效果：

- `app.bili_a.version` 从 `8.90.2` 变成 `8.80.0`
- `app.bili_a.build` 从 `8902100` 变成 `8800000`
- `platform.system.model` 从 `MuMu` 变成 `Pixel 7`
- `platform.system.os_ver` 从 `7.1.2` 变成 `9`
- 其他未写的字段仍然继承 `resources/device/default.yaml`

## 选择建议

- 想完全掌控整份设备配置，用 `device.override.yaml`
- 只想改几个字段，优先用 `device.override+.yaml`

多数情况下，更推荐 `device.override+.yaml`，因为更容易跟随默认配置的后续更新。
