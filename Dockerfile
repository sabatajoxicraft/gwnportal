FROM php:8.2-apache

# Install extensions and msmtp for SMTP mail
RUN apt-get update && apt-get install -y \
    msmtp \
    msmtp-mta \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP to use msmtp for sendmail
RUN echo "sendmail_path = /usr/bin/msmtp -t -i" > /usr/local/etc/php/conf.d/msmtp.ini

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

# Copy and set entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

