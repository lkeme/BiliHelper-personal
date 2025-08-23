#!/bin/sh
set -e

# 源切换
case ${MIRRORS} in
"custom")
    # custom
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-自定义克隆链接 ${Font} \n ======== \n"
    git remote set-url origin ${CUSTOM_CLONE_URL}
    ;;
"0")
    # https://github.com/
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-github.com(RAW|源站) ${Font} \n ======== \n"
    git remote set-url origin https://github.com/lkeme/BiliHelper-personal.git
    ;;
"1")
    # https://ghfast.top/
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-ghfast.top(US|美国) ${Font} \n ======== \n"
    git remote set-url origin https://ghfast.top/https://github.com/lkeme/BiliHelper-personal.git
    ;;
"2")
    # http://gitclone.com/
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-gitclone.com(CN|中国) ${Font} \n ======== \n"
    git remote set-url origin https://gitclone.com/github.com/lkeme/BiliHelper-personal.git
    ;;
"3")
    # https://gh-proxy.com/
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-gh-proxy.com(US|美国) ${Font} \n ======== \n"
    git remote set-url origin https://gh-proxy.com/https://github.com/lkeme/BiliHelper-personal.git
    ;;
"4")
    # https://githubfast.com/
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-githubfast.com(KR|韩国) ${Font} \n ======== \n"
    git remote set-url origin https://githubfast.com/lkeme/BiliHelper-personal.git
    ;;
"5")
    # https://hub.gitmirror.com/
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-hub.gitmirror.com(US|美国) ${Font} \n ======== \n"
    git remote set-url origin https://hub.gitmirror.com/https://github.com/lkeme/BiliHelper-personal.git
    ;;
*)
    echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-github.com(RAW|源站) ${Font} \n ======== \n"
    git remote set-url origin https://github.com/lkeme/BiliHelper-personal.git
    ;;
esac


# 拉取更新
if [ "${AUTO_UPDATE:-1}" = "1" ]; then
  echo -e "\n ======== \n ${Info} ${GreenBG} 正使用 git pull 同步项目 ${Font} \n ======== \n"
  git pull
else
  echo -e "\n ======== \n ${Info} ${RedBG} 已跳过更新同步 ${Font} \n ======== \n"
fi

# 安装依赖
echo -e "\n ======== \n ${Info} ${GreenBG} 安装/更新 项目运行依赖 ${Font} \n ======== \n"
php composer.phar install
echo -e "\n \n \n \n"

# 版本切换
case ${VERSION} in
"1")
    echo -e "\n ======== \n ${Info} ${GreenBG} 正在使用版本V1方案 ${Font} \n ======== \n"
    # 判断类型
    if [[ -f ${V1_CONIFG_PATH} ]]; then
        echo -e "\n ======== \n ${GreenBG} 正在使用外部配置文件 ${Font} \n ======== \n"
    else
        echo -e "${OK} ${GreenBG} 正在使用传入的环境变量进行用户配置。\n 如果需要配置更多选择项，请通过挂载配置文件来传入。具体参考项目中的README。\n https://github.com/lkeme/BiliHelper-personal.git ${Font} \n ======== \n "
        cp /app/conf/user.ini.example /app/conf/user.ini
        sed -i ''"$(cat /app/conf/user.ini -n | grep "username = \"\"" | awk '{print $1}')"'c '"$(echo "username = \"${USER_NAME}\"")"'' ${V1_CONIFG_PATH}
        sed -i ''"$(cat /app/conf/user.ini -n | grep "password = \"\"" | awk '{print $1}')"'c '"$(echo "password = \"${USER_PASSWORD}\"")"'' ${V1_CONIFG_PATH}
    fi

    php index.php
    ;;
"2")
    echo -e "\n ======== \n ${Info} ${GreenBG} 正在使用版本V2方案 ${Font} \n ======== \n"
    # 判断类型
    if [[ -f ${V2_CONIFG_PATH} ]]; then
        echo -e "\n ======== \n ${GreenBG} 正在使用外部配置文件 ${Font} \n ======== \n"
    else
        echo -e "${OK} ${GreenBG} 正在使用传入的环境变量进行用户配置。\n 如果需要配置更多选择项，请通过挂载配置文件来传入。具体参考项目中的README。\n https://github.com/lkeme/BiliHelper-personal.git ${Font} \n ======== \n "
        cp -r /app/profile/example /app/profile/user
        sed -i ''"$(cat /app/profile/user/config/user.ini -n | grep "username = \"\"" | awk '{print $1}')"'c '"$(echo "username = \"${USER_NAME}\"")"'' ${V2_CONIFG_PATH}
        sed -i ''"$(cat /app/profile/user/config/user.ini -n | grep "password = \"\"" | awk '{print $1}')"'c '"$(echo "password = \"${USER_PASSWORD}\"")"'' ${V2_CONIFG_PATH}
    fi

    if [ "$CAPTCHA" == "1" ]; then
        echo -e "\n ======== \n ${Info} ${GreenBG} 正在使用验证码服务 ${Font} \n ======== \n"
        echo -e "\n ======== \n ${Info} ${GreenBG} 验证码服务地址：http://${CAPTCHA_HOST}:${CAPTCHA_PORT} ${Font} \n ======== \n"
        (cd ./captcha && php -S $CAPTCHA_HOST:$CAPTCHA_PORT &) ; (php app.php m:a)
    else
        php app.php m:a
    fi
    ;;
*)
    echo -e "\n ======== \n ${Info} ${RedBG} 错误的版本方案选择 ${Font} \n ======== \n"
    ;;
esac
