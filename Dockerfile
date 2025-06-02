FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user
RUN useradd -G www-data,root -u 1000 -d /home/midd midd
RUN mkdir -p /home/midd/.composer && \
    chown -R midd:midd /home/midd

# Set working directory
WORKDIR /var/www

COPY . /var/www
COPY --chown=midd:midd . /var/www

USER midd

EXPOSE 9000
CMD ["php-fpm"]