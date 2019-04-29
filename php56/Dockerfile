FROM php:5.6-apache

COPY ./php.ini /usr/local/etc/php/

RUN a2enmod headers
RUN a2enmod rewrite

ADD ssl.conf /etc/apache2/sites-available/ssl.conf
ADD entrypoint.sh /opt/entrypoint.sh

RUN chmod a+x /opt/entrypoint.sh
RUN /bin/bash -c "source /opt/entrypoint.sh /etc/apache2/ssl_keys localhost"
RUN a2enmod ssl
RUN a2ensite ssl

RUN usermod -u 1000 www-data \
    && groupmod -g 1000 www-data

RUN apt-get update \
    && apt-get -y install vim wget lsb-release libicu-dev mysql-client \
    && docker-php-ext-install pdo_mysql mysqli intl
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev
RUN docker-php-ext-install gd
