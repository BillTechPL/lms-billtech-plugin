#!/bin/bash
sys_dir=$(awk -F "=" '/^sys_dir/ {gsub(/[ \t]/, "", $2); print $2}' /etc/lms/lms.ini)
openssl genrsa -out lms.pem 2048
openssl rsa -in lms.pem -pubout > lms.pub

cd ../../
composer require guzzlehttp/guzzle:^6.0
composer dump-autoload

cd plugins/BillTech
chmod 0644 cron/*
mkdir /var/log/billtech
eval "echo \"$(cat cron/*)\"" | crontab