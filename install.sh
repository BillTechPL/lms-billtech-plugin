#!/bin/bash

openssl genrsa -out lms.pem 2048
openssl rsa -in lms.pem -pubout > lms.pub

cd ../../
composer require guzzlehttp/guzzle:^6.0
composer dump-autoload

sys_dir=$(awk -F "=" '/^sys_dir/ {gsub(/[ \t]/, "", $2); print $2}' /etc/lms/lms.ini)

mkdir cron
touch cron/billtech-update-links-cron
echo "0,5,10,15,20,25,30,35,40,45,50,55 * * * * bash -lc ${sys_dir}/plugins/BillTech/bin/billtech-update-links.php | ${sys_dir}/plugins/BillTech/bin/timestamp.sh >> /var/log/billtech/\`date +\%Y\%m\%d\`-update-links.log 2>&1" >> cron/billtech-update-links-cron
touch cron/billtech-update-payments-cron
echo "* * * * * bash -lc ${sys_dir}/plugins/BillTech/bin/billtech-update-payments.php | ${sys_dir}/plugins/BillTech/bin/timestamp.sh >> /var/log/billtech/\`date +\%Y\%m\%d\`-update-payments.log 2>&1" >> cron/billtech-update-payments-cron

cd plugins/BillTech
chmod 0755 cron/*
cp cron/* /etc/cron.d/
mkdir /var/log/billtech
cat /etc/cron.d/billtech* | crontab