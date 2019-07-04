#copypasted from github.com/johannfenech/ubuntu18_lamp_server_docker

#WARNING: Replace credentials from line 6 to line 11
FROM ubuntu:18.04
MAINTAINER Johann Fenech, jf@johannfenech.com
USER root

#set your ssh password, php_version, sql database name, username, and password below
ARG sshpass="t0rkv3MADa"
ENV php_version="7.2"
ENV sql_db_name="blog"
ENV sql_db_user="blog"
ENV sql_db_pass="S51@akRv"
WORKDIR /root

#Install basic environment
RUN apt-get -y update && \
    apt-get -y install \
	subversion \
    openssh-server \
	supervisor \
    vim


RUN DEBIAN_FRONTEND=noninteractive \
    apt-get update && \
    apt-get install -y language-pack-en-base &&\
    export LC_ALL=en_US.UTF-8 && \
    export LANG=en_US.UTF-8

RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get install -y software-properties-common
RUN DEBIAN_FRONTEND=noninteractive LC_ALL=en_US.UTF-8 add-apt-repository ppa:ondrej/php

RUN apt-get -y update

RUN DEBIAN_FRONTEND=noninteractive apt-get -y install mysql-server

RUN DEBIAN_FRONTEND=noninteractive apt-get install -y tzdata

RUN apt-get install -y \
	php$php_version \
	php$php_version-bz2 \
	php$php_version-cgi \
	php$php_version-cli \
	php$php_version-common \
	php$php_version-curl \
	php$php_version-dev \
	php$php_version-enchant \
	php$php_version-fpm \
	php$php_version-gd \
	php$php_version-gmp \
	php$php_version-imap \
	php$php_version-interbase \
	php$php_version-intl \
	php$php_version-json \
	php$php_version-ldap \
	php$php_version-mbstring \
	php$php_version-mysqli \
	php$php_version-odbc \
	php$php_version-opcache \
	php$php_version-pgsql \
	php$php_version-phpdbg \
	php$php_version-pspell \
	php$php_version-readline \
	php$php_version-recode \
	php$php_version-snmp \
	php$php_version-sqlite3 \
	php$php_version-sybase \
	php$php_version-tidy \
	php$php_version-xmlrpc \
	php$php_version-xsl

RUN apt-get install apache2 libapache2-mod-php$php_version -y

RUN a2enmod rewrite

#Set up SSH access
RUN mkdir /var/run/sshd
RUN sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config
RUN echo "root:$sshpass" | chpasswd
RUN service ssh restart

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

#Copy Application
COPY . /var/www/html/

COPY ./docker/000-default.conf /etc/apache2/sites-available/

EXPOSE 22 80 3306

CMD ["/usr/bin/supervisord"]

VOLUME ["/var/lib/mysql", "/var/log/mysql", "/var/log/apache2"]

#Create Script in /tmp to create the database
COPY db_dump.sql /tmp/
RUN printf "service mysql restart \n" > /tmp/create_db.sh && \
    printf "sleep 5 \n" >> /tmp/create_db.sh && \
    printf "mysql -uroot  -e \"CREATE DATABASE $sql_db_name;\" \n" >> /tmp/create_db.sh && \
    printf "mysql -uroot  -e \"CREATE USER '$sql_db_user'@'localhost' IDENTIFIED BY '$sql_db_pass';\" \n"  >> /tmp/create_db.sh && \
    printf "mysql -uroot  -e \"GRANT ALL PRIVILEGES ON $sql_db_name.* TO '$sql_db_user'@'localhost';\" \n"  >> /tmp/create_db.sh && \
    printf "mysql -uroot  -e \"FLUSH PRIVILEGES;\" \n"  >> /tmp/create_db.sh && \
    printf "mysql -uroot  $sql_db_name < /tmp/db_dump.sql" >> /tmp/create_db.sh