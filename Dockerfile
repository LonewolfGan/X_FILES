# Railway PHP + Python Dockerfile
FROM dunglas/frankenphp:php8.4-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    python3 \
    python3-pip \
    python3-venv \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli zip

# Create Python virtual environment for NudeNet
RUN python3 -m venv /opt/nudenet-venv
RUN /opt/nudenet-venv/bin/pip install nudenet pillow numpy

# Make venv Python available globally
RUN ln -s /opt/nudenet-venv/bin/python /usr/local/bin/nudenet-python

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app/

# Set permissions
RUN chown -R www-data:www-data /app

# Expose port
EXPOSE 80

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
