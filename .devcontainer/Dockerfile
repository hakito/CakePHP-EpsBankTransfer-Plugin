FROM mcr.microsoft.com/devcontainers/php:1-8.3-bullseye

RUN apt update && apt install -y libicu-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install intl