#!/bin/bash

chmod 644 userpanel/* -R
cp -R userpanel/* ../../userpanel/
rm -rf ../../userpanel/templates_c/*

openssl genrsa -out lms.pem 2048
openssl rsa -in lms.pem -pubout > lms.pub

cd ../../
composer install && composer update
cd userpanel/style/bclean
composer install && composer update