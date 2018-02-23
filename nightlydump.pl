#!/usr/bin/perl

$archivedir = "/usr/local/moodle/pgarchive";

$filename = "$archivedir/7";
if (-e $filename) {
    `rm -rf $filename 2>&1 > /dev/null`;
} elsif (-e "$filename.gz") {
    `rm -f $filename.gz 2>&1 > /dev/null`;
}

for (my $i=6; $i >= 0; $i--) {
    $filename = "$archivedir/$i";
    $newnum = $i + 1;
    if (-e $filename) {
        `mv $filename $archivedir/$newnum 2>&1 > /dev/null`;
    } elsif (-e "$filename.gz") {
        `mv $filename.gz $archivedir/$newnum.gz 2>&1 > /dev/null`;
    }
}

`pg_dump -Fd moodle -j 24 -f $archivedir/inprogress`;

`mv $archivedir/inprogress $archivedir/0`;

`chmod -R 755 $archivedir`;

