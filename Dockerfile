FROM php:8.3-apache

COPY public/ /var/www/html/
COPY src/ /var/www/src/
COPY apache/directoryindex.conf /etc/apache2/conf-enabled/zz-directoryindex.conf

RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data
ENV DATA_DIR=/var/www/data
VOLUME ["/var/www/data"]

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
