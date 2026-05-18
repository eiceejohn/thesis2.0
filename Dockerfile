FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libsqlite3-dev \
        libzip-dev \
        nodejs \
        npm \
        unzip \
        zip \
    && docker-php-ext-install pdo pdo_sqlite zip \
    && a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY package.json ./
RUN npm install

COPY . .

RUN composer dump-autoload --optimize \
    && npm run build \
    && chmod +x docker/start.sh \
    && chown -R www-data:www-data storage bootstrap/cache database

CMD ["docker/start.sh"]
