#!/bin/bash

openssl genrsa -out lms.pem 2048
openssl rsa -in lms.pem -pubout > lms.pub

cd ../../
composer require guzzlehttp/guzzle:^6.0
composer dump-autoload