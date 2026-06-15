FROM php:8.3.11-apache

WORKDIR /var/www
ENV DEBIAN_FRONTEND=noninteractive

ARG WWW_ROOT="/var/www"
ENV WWW_ROOT=$WWW_ROOT

RUN apt-get update -y --allow-insecure-repositories && \
    apt-get install -y --no-install-recommends \
    git \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    libssl-dev \
    pkg-config \
    rsync \
    unzip \
    sudo \
    zip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
    gd \
    intl \
    mbstring \
    opcache \
    pdo_mysql \
    zip

RUN pecl install redis pcov && docker-php-ext-enable redis pcov

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash && \
    apt-get install -y symfony-cli && \
    symfony server:ca:install && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg && \
    echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list && \
    apt update && \
    apt install stripe

ADD docker/apache/entrypoint.sh /entrypoint.sh
RUN chmod a+x /entrypoint.sh && \
    a2enmod rewrite remoteip ssl

RUN echo 'alias ll="ls -al"' >> ~/.bashrc
RUN echo 'alias dfl="symfony console doctrine:database:drop --force && symfony console doctrine:database:create && symfony console d:s:u --force -n && symfony console doctrine:fixtures:load -n"' >> ~/.bashrc
RUN echo 'alias sc="symfony console"' >> ~/.bashrc
RUN echo 'alias scme="symfony console make:entity"' >> ~/.bashrc
RUN echo 'alias scmc="symfony console make:controller"' >> ~/.bashrc
RUN echo 'alias scmcrud="symfony console make:crud"' >> ~/.bashrc
RUN echo 'alias scmf="symfony console make:form"' >> ~/.bashrc
RUN echo 'alias cc="symfony console cache:clear"' >> ~/.bashrc

CMD ["/entrypoint.sh"]

EXPOSE 80
