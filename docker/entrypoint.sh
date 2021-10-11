#!/bin/bash

# Fix files perms
chown -R 1000:www-data /var/www/html
chmod -R 771 /var/www/html

# Enable install tool
touch /var/www/html/typo3conf/ENABLE_INSTALL_TOOL

# Continue with apache job
apache2-foreground
