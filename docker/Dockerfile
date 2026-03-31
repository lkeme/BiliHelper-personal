FROM composer:2 AS composer-bin

FROM php:8.3-alpine

LABEL AUTHOR="Lkeme <Useri@live.cn>"

ARG DEFAULT_BRANCH=master

ENV USER_NAME='' \
    USER_PASSWORD='' \
    CAPTCHA='0' \
    CAPTCHA_HOST='0.0.0.0' \
    CAPTCHA_PORT='50001' \
    CUSTOM_CLONE_URL='https://speed.example.com/example/example.git' \
    MIRRORS='0' \
    AUTO_UPDATE='1' \
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
    curl-dev \
    git \
    tzdata

RUN docker-php-ext-install sockets curl mbstring

RUN ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo Asia/Shanghai > /etc/timezone

COPY composer.json composer.lock /app/
COPY app.php /app/
COPY src /app/src
COPY plugin /app/plugin
COPY resources /app/resources
COPY profile/example /app/profile/example
COPY captcha /app/captcha
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && git init /app \
    && git -C /app config user.email "docker@localhost" \
    && git -C /app config user.name "docker" \
    && git -C /app add app.php composer.json composer.lock src plugin resources profile captcha \
    && git -C /app commit -m "image snapshot" --quiet \
    && git -C /app remote add origin https://github.com/lkeme/BiliHelper-personal.git \
    && composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    && sha1sum /app/composer.lock | awk '{print $1}' > /app/vendor/.composer-lock.hash

ENTRYPOINT ["entrypoint.sh"]
