#!  /bin/bash

# Site configuration options
SITE_TITLE="Dev Site"
ADMIN_USER=admin
ADMIN_PASS=password
ADMIN_EMAIL="admin@localhost.com"
# Space-separated list of plugin ID's to install and activate
PLUGINS="advanced-custom-fields multiple-domain relative-url"
PUBLIC_DOMAIN="0026-2-223-170-8.eu.ngrok.io"
API_KEY="sk_sandbox_c8oLGaI__msxsXbpBDpdtzhOkvKbeEX--QmMyIQPuLL09hmeN8E8MK_NMRdhI6NSzPnzvnqn8ROe1CU3VduybQ"
# Set to true to wipe out and reset your wordpress install (on next container rebuild)
WP_RESET=true

echo "Setting up WordPress"
DEVDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd /var/www/html;
if $WP_RESET ; then
    echo "Resetting WP"
    wp plugin delete $PLUGINS
    wp db reset --yes
    rm wp-config.php;
fi

if [ ! -f wp-config.php ]; then 
    echo "Configuring";
    wp config create --dbhost="db" --dbname="wordpress" --dbuser="wp_user" --dbpass="wp_pass" --skip-check;
    wp core install --url="http://localhost:8080" --title="$SITE_TITLE" --admin_user="$ADMIN_USER" --admin_email="$ADMIN_EMAIL" --admin_password="$ADMIN_PASS" --skip-email;
    wp plugin install $PLUGINS --activate
    
    # Data import
    cd $DEVDIR/data/
    for f in *.sql; do
        wp db import $f
    done

    cd $DEVDIR/
    ls plugins
    cp -r plugins/* /var/www/html/wp-content/plugins

    for p in plugins/*; do
        wp plugin activate "$(basename $p)" --path="/var/www/html"
    done    
    
    cd /var/www/html

    wp plugin install woocommerce --activate
    wp plugin install wordpress-importer  --activate
    wp import /var/www/html/wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=create
    
    wp option update woocommerce_store_address '1 london street'
    wp option update woocommerce_store_city 'london'
    wp option update woocommerce_store_postcode '12345'
    wp option update woocommerce_currency 'GBP'
        
    rm /var/www/html/wp-content/plugins/paymentsense-remote-payments-for-woocommerce
    ln -s /var/workspace/paymentsense-remote-payments-for-woocommerce /var/www/html/wp-content/plugins/paymentsense-remote-payments-for-woocommerce
    wp plugin activate paymentsense-remote-payments-for-woocommerce

    wp option update multiple-domain-domains '{"localhost:8080":{"base":null,"lang":null,"protocol":"auto"},"'"$PUBLIC_DOMAIN"'":{"base":null,"lang":null,"protocol":"auto"}}' --format=json
    # wp option add woocommerce_dojo_settings '{"enabled":"yes","module_options":"","title":"Dojo Checkout","description":"Pay securely by credit or debit card through Dojo.","order_prefix":"WC-","gateway_settings":"","secret_key":"'"$API_KEY"'","capture_mode":"Auto","wallet_enabled":"on"}'  --format=json
else
    echo "Already configured"
fi