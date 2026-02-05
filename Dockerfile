# Stage 1: Build/Setup
FROM php:8.4-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    libpq-dev \
    oniguruma-dev \
    libxml2-dev && \
    docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath xml

# Stage 2: Production
FROM php:8.4-fpm-alpine

# Install runtime dependencies and Nginx
RUN apk add --no-cache \
    libpq \
    oniguruma \
    libxml2 \
    nginx \
    supervisor && \
    mkdir -p /var/log/supervisor && \
    mkdir -p /var/run/nginx && \
    rm -rf /var/cache/apk/*

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Set working directory
WORKDIR /var/www

# Copy application code
COPY public-site-source/. /var/www

# Copy configurations
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R www-data:www-data /var/www

# Expose port 80 and start supervisor
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
