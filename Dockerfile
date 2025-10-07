FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u 1000 -d /home/streamline streamline
RUN mkdir -p /home/streamline/.composer && \
    chown -R streamline:streamline /home/streamline

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
COPY --chown=streamline:streamline . /var/www

# Change current user to streamline
USER streamline

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]