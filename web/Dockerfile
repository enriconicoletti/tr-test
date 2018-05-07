FROM ubuntu:16.04

RUN export DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get -yq upgrade && apt-get -yq install curl apache2 libapache2-mod-fastcgi

# Install filebeat to send logs to logstash
RUN echo "deb http://packages.elastic.co/beats/apt stable main" | tee -a /etc/apt/sources.list.d/beats.list
RUN curl --silent https://packages.elastic.co/GPG-KEY-elasticsearch | apt-key add -
RUN apt-get update && apt-get -yq install filebeat

# Enable apache mods.
RUN a2enmod proxy_fcgi

# Manually set up the apache environment variables
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_PID_FILE /var/run/apache2.pid

# Configure the logfile forwarding
COPY filebeat.yml /etc/filebeat/filebeat.yml

# Expose apache.
EXPOSE 80

# Copy the PHP files to be served by apache
ADD www /var/www/html

# Update the default apache site with the config we created.
ADD apache-config.conf /etc/apache2/sites-enabled/000-default.conf

# Start filebeat and apache
CMD /etc/init.d/filebeat start && \
    /usr/sbin/apache2ctl -D FOREGROUND
