# Windows 本地环境部署指南（PHP >= 8.5）

## 1. 安装 Visual C++ 运行时

PHP 8.5 使用 VS17 编译，运行前需安装对应版本的 Visual C++ Redistributable：

- x64：https://aka.ms/vs/17/release/vc_redist.x64.exe
- x86：https://aka.ms/vs/17/release/vc_redist.x86.exe

## 2. 下载 PHP

从 https://downloads.php.net/~windows/releases/ 下载 PHP 8.5.x Non Thread Safe (NTS) x64：

- 文件名格式：`php-8.5.x-nts-Win32-vs17-x64.zip`
- 下载地址：https://downloads.php.net/~windows/releases/php-8.5.x-nts-Win32-vs17-x64.zip

将 zip 解压到目标目录，例如 `C:\php`。

## 3. 配置 PHP

将解压目录中的 `php.ini-development` 复制为 `php.ini`，并启用必要扩展：

```ini
extension=openssl
extension=json
extension=zlib
extension=mbstring
extension=sqlite3
```

将 PHP 目录添加到系统 PATH：

```
系统属性 → 环境变量 → Path → 新建 → C:\php
```

验证安装：

```shell
php -v
```

## 4. 安装 Composer

从 https://getcomposer.org/download/ 下载并安装 Composer。

验证安装：

```shell
composer -V
```

## 5. 初始化项目

```shell
git clone https://github.com/lkeme/BiliHelper-personal.git
cd BiliHelper-personal
cp -r profile/example profile/user
composer install
```

编辑 `profile/user/config/user.ini`，填写账号密码并按需开启插件。

## 6. 运行

```shell
php app.php
```

详细命令模式参见 [DOC.md](./DOC.md)。
