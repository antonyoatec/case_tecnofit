# Multi-stage build for production optimization
FROM hyperf/hyperf:8.2-alpine-v3.18-swoole AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    zip \
    unzip \
    curl \
    mysql-client

# Set working directory
WORKDIR /opt/www

# Copy composer files
COPY composer.json ./

# Copy application code first
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Set proper permissions
RUN mkdir -p /opt/www/runtime \
    && chmod -R 755 /opt/www/runtime

# Copy and set entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port
EXPOSE 9501

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9501/health || exit 1

# Start command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

