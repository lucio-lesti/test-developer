FROM php:8.0-apache

# Install extensions
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip \
    && docker-php-ext-install pdo_mysql zip

RUN a2enmod rewrite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia tutto il contenuto della cartella dove si trova il Dockerfile nel container
COPY . .

# PUNTA APACHE ALLA SOTTOCARTELLA GIUSTA
ENV APACHE_DOCUMENT_ROOT /var/www/html/backend/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# COMMENTA QUESTA RIGA (La lanceremo a mano dopo l'avvio)
# RUN cd backend && composer install --no-interaction --optimize-autoloader

RUN echo "<Directory /var/www/html/backend/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" >> /etc/apache2/apache2.conf
