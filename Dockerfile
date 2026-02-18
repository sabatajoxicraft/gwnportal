FROM php:8.2-apache

# Install extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable mod_rewrite
RUN a2enmod rewrite

# Add ServerName directive to suppress Apache warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy gwn-portal files
COPY . /var/www/html/

# Copy Apache config to set DocumentRoot to /var/www/html/public
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Permissions
RUN chown -R www-data:www-data /var/www/html

