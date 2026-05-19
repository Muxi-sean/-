FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache nginx bash curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Configure PHP-FPM
RUN sed -i 's|listen = .*|listen = 127.0.0.1:9000|g' /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app/

# Copy Nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

# Copy startup script
RUN chmod +x /app/start.sh

EXPOSE 80

CMD ["/app/start.sh"]
