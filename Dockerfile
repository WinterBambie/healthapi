FROM php:8.2-apache

# SOLO instalar extensiones necesarias
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Activar rewrite
RUN a2enmod rewrite

# 🔴 IMPORTANTE: desactivar MPM conflictivo
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80