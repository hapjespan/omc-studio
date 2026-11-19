FROM php:8.4-cli

# System packages, PHP extensions for Laravel on Postgres, and Node for the
# React frontend (Vite). Debian 13 (trixie) carries gh in its own repos.
#
# python3 is not in php:8.4-cli and the workflow scripts in .workflow/ need it,
# so it is installed here explicitly rather than assumed.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git gh curl ca-certificates unzip python3 xvfb \
        libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip intl bcmath gd \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://claude.ai/install.sh | bash

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PATH="/root/.local/bin:$PATH"

# uv and the Spec Kit CLI, baked into the image so the toolchain version travels
# with the project instead of with the host. Spec Kit is pinned, because an
# unpinned install makes every rebuild a silent upgrade of the tool the whole
# workflow graph runs on. Upgrading is a deliberate bump here plus a rebuild.
# Claude Code is deliberately not pinned: it updates itself either way.
ARG SPECIFY_VERSION=0.16.4
RUN curl -LsSf https://astral.sh/uv/install.sh | sh \
    && uv tool install "specify-cli==${SPECIFY_VERSION}"

# Playwright browsers and their system packages for the end-to-end tests, at the
# version package-lock.json pins; a bump there is a bump here plus a rebuild.
ARG PLAYWRIGHT_VERSION=1.65.1
RUN npx --yes playwright@${PLAYWRIGHT_VERSION} install --with-deps chromium firefox webkit \
    && rm -rf /var/lib/apt/lists/*
