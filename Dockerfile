FROM php:alpine

WORKDIR /app

RUN docker-php-ext-install sockets

RUN apk add --no-cache git && \
    git clone https://github.com/lkeme/BiliHelper-personal.git /app && \
    php -r "copy('https://install.phpcomposer.com/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php composer.phar install && \
    rm -r /var/cache/apk && \
    rm -r /usr/share/man

ENTRYPOINT git pull && \
    rm composer.lock && \
    php composer.phar clearcache && \
    php composer.phar install && \
    php index.php
