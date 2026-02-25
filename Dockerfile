FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor bash tzdata curl sqlite-dev
RUN docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www/html

COPY . /var/www/html

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY startup.sh /startup.sh

RUN sed -i 's/\r$//' /startup.sh \
    && chmod +x /startup.sh \
    && mkdir -p /run/nginx /var/log/supervisor /var/www/html/db /var/www/html/public/assets/images/uploads/logos /etc/periodic/15min \
    && cp /var/www/html/cronjobs/15min/subtrack-reminders /etc/periodic/15min/subtrack-reminders \
    && sed -i 's/\r$//' /etc/periodic/15min/subtrack-reminders \
    && chmod +x /etc/periodic/15min/subtrack-reminders

EXPOSE 80

CMD ["/startup.sh"]
