#!/bin/sh
set -eu
# Fresh installation and additive, checksum-tracked upgrades. Back up before updating images.
php /var/www/scripts/migrate.php
exec apache2-foreground
