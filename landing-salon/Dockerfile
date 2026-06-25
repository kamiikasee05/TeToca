FROM php:8.2-fpm-alpine
RUN apk add --no-cache libpng-dev libjpeg-turbo-dev && docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd && rm -rf /var/cache/apk/*
RUN adduser -D -u 1000 app
COPY php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf
COPY php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini
WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]