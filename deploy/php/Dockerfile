FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql mysqli && \
    apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev && \
    docker-php-ext-install curl mbstring && rm -rf /var/lib/apt/lists/*
RUN a2enmod headers rewrite
COPY frontend/ /var/www/html/
COPY scripts/ /var/www/scripts/
COPY database/ /var/www/database/
RUN chown -R www-data:www-data /var/www/html
