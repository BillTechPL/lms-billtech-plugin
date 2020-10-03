#!/bin/bash

openssl genrsa -out lms.pem 2048
openssl rsa -in lms.pem -pubout > lms.pub

cd ../../
composer require guzzlehttp/guzzle:^6.0
composer dump-autoload

cd plugins/BillTech
chmod 0644 cron/*
cp cron/* /etc/cron.d/
mkdir /var/log/billtech
cat /etc/cron.d/billtech* | crontab