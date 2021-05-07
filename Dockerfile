FROM php:7.4-apache

RUN apt-get update && apt-get install -y \
	zip \
	unzip \
	git

COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
COPY start-apache /usr/local/bin
RUN a2enmod rewrite

RUN mkdir /ieam
RUN chown -R www-data:www-data /ieam

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy application source
COPY src /var/www/
COPY horizon.crt /var/www/
RUN chown -R www-data:www-data /var/www

# Set working directory
WORKDIR /var/www

RUN composer update
RUN composer install

CMD ["start-apache"]
