FROM php:7.1-fpm

RUN echo "Install redis by pecl"
RUN pecl install redis-3.1.2

COPY php.ini /usr/local/etc/php/
