# Imagen base de Snipe-IT
FROM snipe/snipe-it:latest

# Variables de entorno (puedes configurarlas luego en Northflank)
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost \
    DB_CONNECTION=mysql \
    DB_HOST=db \
    DB_PORT=3306 \
    DB_DATABASE=${DB_DATABASE} \
    DB_USERNAME=${DB_USERNAME} \
    DB_PASSWORD=${DB_PASSWORD}

# Exponer el puerto del contenedor
EXPOSE 80

# Directorio de trabajo
WORKDIR /var/www/html

# Comando por defecto (mantiene el contenedor corriendo)
CMD ["apache2-foreground"]
