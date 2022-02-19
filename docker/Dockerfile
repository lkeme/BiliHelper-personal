FROM php:alpine

#MAINTAINER zsnmwy <szlszl35622@gmail.com>
LABEL AUTHOR = "Lkeme <Useri@live.cn>"

ENV USER_NAME='' \
    USER_PASSWORD='' \
    REPO_URL='https://github.com/' \
    CUSTOM_CLONE_URL='https://speed.example.com/example/example.git' \
    MIRRORS="0" \
    CONIFG_PATH='/app/conf/user.ini' \
    Green="\\033[32m" \
    Red="\\033[31m" \
    GreenBG="\\033[42;37m" \
    RedBG="\\033[41;37m" \
    Font="\\033[0m" \
    Green_font_prefix="\\033[32m" \
    Green_background_prefix="\\033[42;37m" \
    Font_color_suffix="\\033[0m" \
    Info="${Green}[信息]${Font}" \
    OK="${Green}[OK]${Font}" \
    Error="${Red}[错误]${Font}"

WORKDIR /app

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories
#RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.tuna.tsinghua.edu.cn/g' /etc/apk/repositories
RUN docker-php-ext-install sockets

#RUN if [ "${CN}" = true ]; then export REPO_URL="https://github.com.cnpmjs.org"; fi

#RUN set -ex \
#    && ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
#    && echo "Asia/Shanghai" > /etc/timezone \

RUN apk add --no-cache git && \
    git clone ${REPO_URL}/lkeme/BiliHelper-personal.git --depth=1 /app && \
    cp -f /app/docker/entrypoint.sh /usr/local/bin/entrypoint.sh && \
    chmod 777 /usr/local/bin/entrypoint.sh && \
    php -r "copy('https://install.phpcomposer.com/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php composer.phar install && \
    rm -r /var/cache/apk && \
    rm -r /usr/share/man

ENTRYPOINT ["entrypoint.sh"]