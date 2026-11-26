FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git gh curl ca-certificates unzip python3 xvfb \
        libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip intl bcmath gd \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://claude.ai/install.sh | bash

RUN printf 'zend.exception_ignore_args=On\nmemory_limit=256M\n' > "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PATH="/root/.local/bin:$PATH"

ARG SPECIFY_VERSION=0.16.4
RUN curl -LsSf https://astral.sh/uv/install.sh | sh \
    && uv tool install "specify-cli==${SPECIFY_VERSION}"

ARG PLAYWRIGHT_VERSION=1.65.1
RUN npx --yes playwright@${PLAYWRIGHT_VERSION} install --with-deps chromium firefox webkit \
    && rm -rf /var/lib/apt/lists/*
