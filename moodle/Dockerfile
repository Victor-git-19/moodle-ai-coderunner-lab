FROM php:8.3.28-apache-bookworm

ARG MOODLE_VERSION=5.2.1
ARG CODERUNNER_VERSION=5.9.2
ARG CODERUNNER_BEHAVIOUR_VERSION=1.4.5

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" exif gd intl mbstring mysqli opcache soap zip \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/* /var/www/html/*

WORKDIR /var/www/html

# Moodle и сторонние плагины закреплены на проверенных версиях.
RUN curl -fsSL "https://github.com/moodle/moodle/archive/refs/tags/v${MOODLE_VERSION}.tar.gz" \
        | tar -xz --strip-components=1 \
    && mkdir -p public/question/type/coderunner public/question/behaviour/adaptive_adapted_for_coderunner \
    && curl -fsSL "https://github.com/trampgeek/moodle-qtype_coderunner/archive/refs/tags/v${CODERUNNER_VERSION}.tar.gz" \
        | tar -xz --strip-components=1 -C public/question/type/coderunner \
    && curl -fsSL "https://github.com/trampgeek/moodle-qbehaviour_adaptive_adapted_for_coderunner/archive/refs/tags/v${CODERUNNER_BEHAVIOUR_VERSION}.tar.gz" \
        | tar -xz --strip-components=1 -C public/question/behaviour/adaptive_adapted_for_coderunner \
    && chown -R root:www-data /var/www/html

COPY moodle/php.ini /usr/local/etc/php/conf.d/moodle.ini
# Собственный плагин и курс добавляются при сборке, а не вручную в контейнере.
COPY --chown=root:www-data moodle/local_aicodehelper /var/www/html/public/local/aicodehelper
COPY --chown=root:www-data course /opt/python-course
COPY --chmod=755 moodle/entrypoint.sh /usr/local/bin/moodle-entrypoint

ENTRYPOINT ["moodle-entrypoint"]
CMD ["apache2-foreground"]
