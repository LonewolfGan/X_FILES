FROM php:8.1-apache


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


RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli zip


RUN a2enmod rewrite


RUN python3 -m venv /opt/nudenet-venv \
    && /opt/nudenet-venv/bin/pip install --no-cache-dir nudenet pillow numpy


RUN ln -s /opt/nudenet-venv/bin/python /usr/local/bin/nudenet-python


RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>' > /etc/apache2/conf-available/project.conf \
    && a2enconf project


WORKDIR /var/www/html


COPY . /var/www/html/


RUN chown -R www-data:www-data /var/www/html

ENV PORT=80
EXPOSE 80


CMD ["apache2-foreground"]
ENV PYTHONUNBUFFERED=1