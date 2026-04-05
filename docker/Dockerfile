FROM composer:2 AS composer-bin

FROM php:8.4-alpine

LABEL AUTHOR="Lkeme <Useri@live.cn>"

ARG DEFAULT_BRANCH=master

ENV USER_NAME='' \
    USER_PASSWORD='' \
    CAPTCHA='0' \
    CAPTCHA_HOST='0.0.0.0' \
    CAPTCHA_PORT='50001' \
    BRANCH="${DEFAULT_BRANCH}" \
    CONFIG_PATH='/app/profile/user/config/user.ini' \
    Green="\\033[32m" \
    Red="\\033[31m" \
    GreenBG="\\033[42;37m" \
    RedBG="\\033[41;37m" \
    Font="\\033[0m" \
    Info="${Green}[信息]${Font}" \
    OK="${Green}[OK]${Font}" \
    Error="${Red}[错误]${Font}" \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories

RUN apk add --no-cache \
    ${PHPIZE_DEPS} \
    linux-headers \
    oniguruma-dev \
    git \
    tzdata

RUN ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo Asia/Shanghai > /etc/timezone

COPY composer.json composer.lock /app/
COPY app.php /app/
COPY src /app/src
COPY plugins /app/plugins
COPY resources /app/resources
COPY profile/example /app/profile/example
COPY captcha /app/captcha
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    && sha1sum /app/composer.lock | awk '{print $1}' > /app/vendor/.composer-lock.hash

ENTRYPOINT ["entrypoint.sh"]
