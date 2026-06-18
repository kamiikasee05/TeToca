FROM php:8.2-cli
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev && docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
EXPOSE 8080
CMD ["php", "-d", "max_execution_time=30", "-d", "max_input_time=60", "-d", "upload_max_filesize=20M", "-d", "post_max_size=25M", "-S", "0.0.0.0:8080", "-t", "/var/www/html"]