FROM webdevops/php-apache-dev:8.1 

ENV MAGENTO_PUBLIC_KEY 'fc69555fee735f94b4155e5b7fc82db9'
ENV MAGENTO_PRIVATE_KEY '2fa39ba0cab20b43500d25b778a3c03c'

#Install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

#install Composer
WORKDIR /tmp
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
	&& php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer

#add vscode user
RUN useradd -ms /bin/bash vscode \
	&& usermod -aG www-data vscode

WORKDIR /var/www/html
USER www-data

USER root
RUN chown -R www-data:www-data /var/www/html/
RUN chmod g+w -R /var/www/html/
RUN find /var/www/html/ -type d -exec chmod g+s {} \;

#Create new Magento app
WORKDIR /app
RUN composer config -g http-basic.repo.magento.com $MAGENTO_PUBLIC_KEY $MAGENTO_PRIVATE_KEY
RUN composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition==2.4.5 .  

# Setup sample data shop
RUN echo '{"http-basic": {"repo.magento.com": { "username": "'$MAGENTO_PUBLIC_KEY'","password": "'$MAGENTO_PRIVATE_KEY'"}}}' > auth.json
RUN php bin/magento sampledata:deploy

# Give full permissions to multiple directories
RUN chmod 777 -R /app/var
RUN chmod 777 -R /app/generated
RUN chmod 777 -R /app/app
RUN chmod 777 -R /app/pub