#!/bin/bash
echo "*** Purge Caches ***"
for folder in /var/www/html/*20*/
do
	echo "Purge ${folder}"
	php ${folder}/admin/cli/purge_caches.php
done
date
