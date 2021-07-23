#!/bin/sh
set -e

# 源切换
case ${MIRRORS} in
"0")
  echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-github.com ${Font} \n ======== \n"
  git remote set-url origin https://github.com/lkeme/BiliHelper-personal.git
  ;;
"1")
  echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-ghproxy.com ${Font} \n ======== \n"
  git remote set-url origin https://ghproxy.com/https://github.com/lkeme/BiliHelper-personal.git
  ;;
"2")
  echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-github.com.cnpmjs.org ${Font} \n ======== \n"
  git remote set-url origin https://github.com.cnpmjs.org/lkeme/BiliHelper-personal.git
  ;;
*)
  echo -e "\n ======== \n ${Info} ${GreenBG} 切换源-github.com ${Font} \n ======== \n"
  git remote set-url origin https://github.com/lkeme/BiliHelper-personal.git
  ;;
esac

# 拉取更新
echo -e "\n ======== \n ${Info} ${GreenBG} 正使用 git pull 同步项目 ${Font} \n ======== \n"
git pull

# 安装依赖
echo -e "\n ======== \n ${Info} ${GreenBG} 安装/更新 项目运行依赖 ${Font} \n ======== \n"
php composer.phar install
echo -e "\n \n \n \n"

# 判断类型
if [[ -f ${CONIFG_PATH} ]]; then
  echo -e "\n ======== \n ${GreenBG} 正在使用外部配置文件 ${Font} \n ======== \n"
else
  echo -e "${OK} ${GreenBG} 正在使用传入的环境变量进行用户配置。\n 如果需要配置更多选择项，请通过挂载配置文件来传入。具体参考项目中的README。\n https://github.com/lkeme/BiliHelper-personal.git ${Font} \n ======== \n "
  cp /app/conf/user.ini.example /app/conf/user.ini
  sed -i ''"$(cat /app/conf/user.ini -n | grep "username = \"\"" | awk '{print $1}')"'c '"$(echo "username = \"${USER_NAME}\"")"'' ${CONIFG_PATH}
  sed -i ''"$(cat /app/conf/user.ini -n | grep "password = \"\"" | awk '{print $1}')"'c '"$(echo "password = \"${USER_PASSWORD}\"")"'' ${CONIFG_PATH}
fi

php index.php
