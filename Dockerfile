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
    bash \
    git

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring xml zip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure PHP-FPM to listen on TCP
RUN sed -i 's|listen = .*|listen = 127.0.0.1:9000|g' /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /app

# Copy all source code
COPY . /app/

# Install ThinkPHP 6
WORKDIR /app/backend
RUN composer require topthink/framework:^6.1 --no-interaction || true
RUN composer require elasticsearch/elasticsearch:^8.0 --no-interaction || true
RUN composer require topthink/think-cors:^2.0 --no-interaction || true
RUN composer install --no-dev --optimize-autoloader --no-interaction || true
RUN mkdir -p /app/backend/runtime && chmod 777 /app/backend/runtime

# Build frontend
WORKDIR /app/frontend
RUN npm install && npm run build

# Copy Nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

# Copy startup script
RUN chmod +x /app/start.sh

EXPOSE 80

CMD ["/app/start.sh"]
