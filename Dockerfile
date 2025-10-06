# Imagen base oficial de Snipe-IT
FROM snipe/snipe-it:latest

# Establecemos el directorio de trabajo
WORKDIR /var/www/html

# Copiamos los archivos del proyecto
COPY . .

# Copiamos el archivo de entorno
COPY .env /var/www/html/.env

# Aseguramos permisos correctos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Exponemos el puerto correcto
EXPOSE 80

# Comando para iniciar Apache (Snipe-IT usa Apache)
CMD ["apache2-foreground"]
