#!/usr/bin/env bash

# get the user that owns our app here
APP_USER=www-kptv;
APP_PATH="/home/www-kptv/htdocs/app.kptv.im"

# allow root
export COMPOSER_ALLOW_SUPERUSER=1;

# update all packages
composer --working-dir=$APP_PATH/ update;

# dump the composer autoloader and force it to regenerate
composer --working-dir=$APP_PATH/ dumpautoload -o -n;

# clear our caches
systemctl restart php8.4-fpm
redis-cli flushall
systemctl restart nginx

# reset permissions
chown -R $APP_USER:$APP_USER $APP_PATH
find $APP_PATH -type d -exec chmod 755 {} \;
find $APP_PATH -type f -exec chmod 644 {} \;
find $APP_PATH -name "*.py" -exec chmod +x {} \;
find $APP_PATH -name "*.sh" -exec chmod +x {} \;
