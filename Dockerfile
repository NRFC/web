# Multi-stage build for development and production
FROM php:8.4-apache as base

# Set build argument for version
ARG VERSION=8.4

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    libssl-dev \
    libxml2-dev \
    libicu-dev \
    default-mysql-client \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension with various image format support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo_mysql \
    zip \
    exif \
    intl \
    bcmath \
    opcache \
    soap

# Enable Apache mod_rewrite
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Development stage
FROM base as dev

# Install Xdebug for development
RUN pecl install xdebug

# Copy development configuration
COPY docker/development/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Set development-specific settings
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/error-reporting.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/conf.d/error-reporting.ini

# Change document root if needed (optional)
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# Create a non-root user for development
RUN groupadd -g 1001 www-user && \
    useradd -u 1001 -g www-user -m www-user && \
    chown -R www-user:www-user /var/www/html && \
    sed -i '/DocumentRoot.*APACHE_DOCUMENT_ROOT}/a\\n\t# Allow .htaccess overrides in document root\n\t<Directory \"\${APACHE_DOCUMENT_ROOT}\">\n\t\tOptions Indexes FollowSymLinks\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>' /etc/apache2/sites-available/000-default.conf


USER www-user

# Production stage
FROM base as production

# Install additional production optimizations
RUN docker-php-ext-install opcache

# Copy production configuration
COPY docker/production/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/production/apache-security.conf /etc/apache2/conf-available/security.conf

# Set production PHP settings
RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/error-reporting.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/error-reporting.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/error-reporting.ini

# Create a non-root user for production
RUN groupadd -g 1001 www && \
    useradd -u 1001 -g www -r -s /bin/false www && \
    chown -R www:www /var/www/html

USER www

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80
