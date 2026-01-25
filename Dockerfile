ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

RUN set -eux; \
	docker-php-ext-install mysqli; \
	a2enmod rewrite

RUN set -eux; \
	{ \
		echo '<Directory /var/www/html>'; \
		echo '    AllowOverride All'; \
		echo '</Directory>'; \
	} > /etc/apache2/conf-available/phppickem.conf; \
	a2enconf phppickem
