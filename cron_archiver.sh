#!/bin/bash
#this script is for archiving the cron output log file monthly
crondir="/usr/local/espace/prod/cronarchive/"
datestamp=$(date +%Y%m%d%k%M)
echo "Creating cronout.$datestamp"
mv $crondir/cronout $crondir/cronout.$datestamp
touch $crondir/cronout
