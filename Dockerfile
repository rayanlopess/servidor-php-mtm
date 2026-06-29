FROM php:8.2-apache

# Copia os arquivos do seu projeto para a pasta do servidor Apache
COPY . /var/www/html/

# Ativa o módulo de reescrita do Apache se precisar (opcional)
RUN a2enmod rewrite

# Expõe a porta padrão que o Render espera
EXPOSE 80