#!  /bin/bash

cd /app

if [ ! -f var/log/system.log ]; then
    echo "Configuring";

	#Setup Magento
    php bin/magento setup:install \
    --admin-firstname=John \
    --admin-lastname=Doe \
    --admin-email=johndoe@example.com \
    --admin-user=admin \
    --admin-password='SomePassword123' \
    --base-url=http://local.domain.com \
    --base-url-secure=https://local.domain.com \
    --backend-frontname=admin \
    --db-host=db \
    --db-name=magento \
    --db-user=root \
    --db-password=root \
    --use-rewrites=1 \
    --language=en_GB \
    --currency=GBP \
    --timezone=Europe/London \
    --use-secure-admin=1 \
    --admin-use-security-key=1 \
    --session-save=files \
    --use-sample-data \
    --elasticsearch-host=elasticsearch
    
    #Enable developer mode to see exceptions on the screen and not having to dig through logs
    php bin/magento deploy:mode:set developer
    
    #Create directory for plugins
    mkdir /app/app/code    
	
	#Link source directory with the plugin directory
    ln -s /var/workspace/Paymentsense /app/app/code/Paymentsense

    #Enable Paymentsense Plugin
    php bin/magento module:enable Paymentsense_RemotePayments --clear-static-content
    php bin/magento setup:upgrade
    #php bin/magento setup:static-content:deploy
    
    #Disable Two Factor Authentication for admin user
    bin/magento module:disable Magento_TwoFactorAuth
    
    bin/magento setup:di:compile    
else
    echo "Already configured"
fi

