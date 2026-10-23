# Dockerfile — packages the PHP site together with Apache.
# (MySQL runs in its own container; see docker-compose.yml.)
FROM php:8.2-apache

# PDO's MySQL driver is not included in the base image
RUN docker-php-ext-install pdo pdo_mysql

# PHP's default upload limit is 2 MB; our rule is 3 MB
RUN printf "upload_max_filesize=4M\npost_max_size=8M\n" > /usr/local/etc/php/conf.d/uploads.ini

# Copy the project into Apache's web folder
COPY . /var/www/html/

# The uploads folder must be writable by Apache's user (www-data)
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads
