#!/usr/bin/env bash
# Azure App Service Linux PHP (nginx image) startup hook.
# Swap in the Laravel-aware nginx server block (root -> /public) and
# reload nginx so requests are routed via public/index.php.
set -e

cp /home/site/wwwroot/nginx.conf /etc/nginx/sites-available/default
service nginx reload
