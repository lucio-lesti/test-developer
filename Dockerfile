FROM php:8.0-apache

# Install extensions
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip \
    && docker-php-ext-install pdo_mysql zip

RUN a2enmod rewrite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy the entire 'test' folder contents into the container
COPY . .

# POINT APACHE TO THE SUBFOLDER
# We tell Apache the website root is actually in test_backend/public
ENV APACHE_DOCUMENT_ROOT /var/www/html/test_backend/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Run composer inside the subfolder where composer.json lives
RUN cd test_backend && composer install --no-interaction --optimize-autoloader


# Add this line to allow .htaccess to work correctly without looping
RUN echo "<Directory /var/www/html/test_backend/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" >> /etc/apache2/apache2.conf