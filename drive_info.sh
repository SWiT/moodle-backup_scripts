#!/bin/bash
ls -l /dev/disk/by-id/ata* | grep -v "\-part"
#for drv in $(ls /dev/disk/by-id/ata* | grep -v "\-part"); do
#  echo "$drv"
#done
