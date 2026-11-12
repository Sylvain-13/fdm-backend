FROM php:8.3-apache

# Pilote MySQL pour PDO
RUN docker-php-ext-install pdo pdo_mysql

# Réécriture + prise en compte des .htaccess (protection de config/, lib/, etc.)
RUN a2enmod rewrite \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copie du projet dans la racine web
COPY . /var/www/html/

# Apache doit écouter sur $PORT (fourni par Railway), pas sur le 80 en dur
# Apache doit écouter sur $PORT (fourni par Railway), pas sur le 80 en dur
CMD bash -c '\
  sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf; \
  sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf; \
  mkdir -p /var/www/html/uploads /var/www/html/logs; \
  chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs; \
  apache2-foreground'