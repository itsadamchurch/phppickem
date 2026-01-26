ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

RUN set -eux; \
	docker-php-ext-install mysqli; \
	a2enmod rewrite

RUN set -eux; \
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"; \
	php composer-setup.php --install-dir=/usr/local/bin --filename=composer; \
	php -r "unlink('composer-setup.php');"

COPY docker/entrypoint.sh /usr/local/bin/phppickem-entrypoint
RUN chmod +x /usr/local/bin/phppickem-entrypoint

RUN set -eux; \
	{ \
		echo '<Directory /var/www/html>'; \
		echo '    AllowOverride All'; \
		echo '</Directory>'; \
	} > /etc/apache2/conf-available/phppickem.conf; \
	a2enconf phppickem

ENTRYPOINT ["phppickem-entrypoint"]
