FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    nodejs \
    npm \
    curl \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    libxml2-dev \
    autoconf \
    gcc \
    g++ \
    make \
    linux-headers \
    bash

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring xml zip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure PHP-FPM to listen on TCP
RUN sed -i 's|listen = .*|listen = 127.0.0.1:9000|g' /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /app

# Copy and install backend dependencies
COPY backend/composer.json /app/backend/composer.json
WORKDIR /app/backend
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy backend source code
COPY backend/ /app/backend/

# Ensure runtime directory is writable
RUN mkdir -p /app/backend/runtime && chmod 777 /app/backend/runtime

# Copy and build frontend
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm install
COPY frontend/ ./
RUN npm run build

# Copy Nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

# Remove default server block if exists
RUN rm -f /etc/nginx/conf.d/default.conf

# Copy startup script
COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh

EXPOSE 80

CMD ["/app/start.sh"]
