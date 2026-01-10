FROM php:8.2-fpm

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip nginx

# Instala extensões do PHP necessárias para o Laravel e MySQL
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Define o diretório de trabalho
WORKDIR /var/www/html

# Copia o código para o servidor
COPY . .

# Instala o Composer (Se você não tiver a pasta vendor no git)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Ajusta permissões para o Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Porta que o Render usa
EXPOSE 80

# Comando para iniciar o servidor (Exemplo simplificado)
CMD php artisan serve --host=0.0.0.0 --port=80