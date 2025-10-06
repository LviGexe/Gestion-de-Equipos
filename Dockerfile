# Usa la imagen oficial de Snipe-IT
FROM snipe/snipe-it:latest

# Define el puerto (según tu .env)
EXPOSE 80

# Copia las variables de entorno si existen
COPY .env /var/www/html/.env

# Comando de arranque de Snipe-IT (Laravel)
CMD ["apache2-foreground"]

