FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx bash curl

RUN docker-php-ext-install pdo pdo_mysql

RUN sed -i 's|listen = .*|listen = 127.0.0.1:9000|g' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app

# Copy only the necessary files to avoid recursive symlinks
COPY public /app/public
COPY api /app/api
COPY start.sh /app/start.sh
COPY nginx.conf /etc/nginx/http.d/default.conf

RUN chmod +x /app/start.sh

EXPOSE 80

CMD ["/app/start.sh"]
