FROM php:8.2-fpm-alpine

# Cài đặt các extension mở rộng cần thiết cho MySQL và Opcache
RUN apk add --no-cache \
        bash \
        curl \
        tzdata \
    && docker-php-ext-install pdo pdo_mysql mysqli opcache

# Cấu hình múi giờ Việt Nam
ENV TZ=Asia/Ho_Chi_Minh
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Tạo thư mục log ứng dụng và phân quyền cho user www-data
RUN mkdir -p /var/log/app \
    && chown -R www-data:www-data /var/log/app \
    && chmod -R 775 /var/log/app

# Thiết lập thư mục làm việc
WORKDIR /var/www/html

# Sao chép mã nguồn ứng dụng vào container
COPY app/ /var/www/html/
COPY public/ /var/www/html/public/

# Phân quyền tệp tin cho www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Chuyển sang user www-data để đảm bảo nguyên tắc Least Privilege (Hardening)
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
