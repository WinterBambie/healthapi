FROM php:8.2-apache

# Instalar extensiones MySQL (ESTO ARREGLA TU ERROR)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar rewrite (API friendly)
RUN a2enmod rewrite

# Copiar proyecto
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html

# Puerto Apache
EXPOSE 80