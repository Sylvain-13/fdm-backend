# Déploiement de test — serveur PHP intégré (pas d'Apache, pas de MPM)
FROM php:8.3-cli

# Pilote MySQL pour PDO
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html
COPY . /var/www/html/
RUN mkdir -p uploads logs

# Railway fournit $PORT ; le routeur gère la sécurité des dossiers sensibles
CMD ["sh","-c","php -S 0.0.0.0:${PORT:-8080} -t /var/www/html /var/www/html/router.php"]