<?php

/**
 * Root proxy for Azure App Service Linux PHP, whose default Apache
 * DocumentRoot is /home/site/wwwroot (the repo root) rather than
 * public/. The .htaccess rewrite would handle this if AllowOverride
 * were enabled, but the App Service base image does not enable it,
 * so a tiny PHP entry point at the root is the most reliable route.
 *
 * Static assets in public/* won't be reachable from / without the
 * rewrite. The current landing page has no external assets so this
 * is fine; revisit when the app starts shipping real static files.
 */

require __DIR__ . '/public/index.php';
