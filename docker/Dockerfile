FROM php:alpine

#MAINTAINER zsnmwy <szlszl35622@gmail.com>
LABEL AUTHOR = "Lkeme <Useri@live.cn>"

ENV USER_NAME='' \
    USER_PASSWORD='' \
    REPO_URL='https://github.com/' \
    CUSTOM_CLONE_URL='https://speed.example.com/example/example.git' \
    MIRRORS="0" \
    VERSION="2" \
    V1_CONIFG_PATH='/app/conf/user.ini' \
    V2_CONIFG_PATH='/app/profile/user/config/user.ini' \
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

# Change timezone
RUN set -x; \
    ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime; \
    echo Asia/Shanghais > /etc/timezone

# composer
#RUN curl https://mirrors.aliyun.com/composer/composer.phar -s -S -o /usr/local/bin/composer && \
#    chmod +x /usr/local/bin/composer && composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
#ENV PATH=/root/.composer/vendor/bin:$PATH

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
