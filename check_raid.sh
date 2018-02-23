#!/bin/bash
#run as root
#this script is for monitoring the RAID 6 array for failed disks and free space
email="online@oakland.edu"

/sbin/mdadm --detail /dev/md0
echo

fails=$(/sbin/mdadm --detail /dev/md0 | grep "Failed Devices : 0")
if [ "$fails" = " Failed Devices : 0" ]
then
	echo 'No failed devices.'
else
	echo 'Failed devices. Sending messages.'
	echo "'$fails'"
	message="WARNING: A drive has failed in the https://moodlebackup.oakland.edu RAID 6 array."
	message="$message\n\n$(/sbin/mdadm --detail /dev/md0)"
	message="$message\n\n$(dmesg | grep error)"
	echo -e "$message" | mail -s "SERVER_ERROR: DRIVE FAILURE" "$email"
fi

spare=$(/sbin/mdadm --detail /dev/md0 | grep "Spare Devices : 1")
if [ "$spare" = "  Spare Devices : 1" ]
then
	echo 'Spare device found.'
else
	echo 'No spare devices. Sending messages.'
	echo "'$fails'"
	message="WARNING: No spare devices left in the https://moodlebackup.oakland.edu RAID 6 array."
	message="$message\n\n$(/sbin/mdadm --detail /dev/md0)"
	message="$message\n\n$(dmesg | grep error)"
	echo -e "$message" | mail -s "SERVER_ERROR: SPARE MISSING" "$email"
fi

percentfree=$(df -h | grep "/dev/md0" | awk '{print $5}' | cut -d'%' -f1 )
if (( $percentfree <= 5 ))
then
	echo 'Low disk space. Sending messages.'
        message="WARNING: drive space is low on the https://moodlebackup.oakland.edu raid drive"
        message="$message\n\n$(df -h)"
        echo -e "$message" | mail -s "SERVER_ERROR: LOW DISK SPACE" "$email"
else
	echo "Disk space OK."
fi

percentfree=$(df -h | grep "/dev/sda2" | awk '{print $5}' | cut -d'%' -f1 )
if (( $percentfree <= 5 ))
then
	echo 'Low disk space. Sending messages.'
        message="WARNING: drive space is low on the https://moodlebackup.oakland.edu system/DB drive"
        message="$message\n\n$(df -h)"
        echo -e "$message" | mail -s "SERVER_ERROR: LOW DISK SPACE" "$email"
else
	echo "Disk space OK."
fi
