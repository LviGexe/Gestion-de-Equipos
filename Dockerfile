# Usa la imagen oficial de Snipe-IT
FROM snipe/snipe-it:latest

# Copia tu archivo de entorno
COPY .env /var/www/html/.env

# Define el directorio de trabajo
WORKDIR /var/www/html

# Expone el puerto HTTP
EXPOSE 80

# Usa el entrypoint correcto de la imagen
ENTRYPOINT ["/entrypoint.sh"]

# Inicia Apache
CMD ["apache2-foreground"]

