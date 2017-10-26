#!/bin/bash

chmod 660 userpanel/* -R
cp -R userpanel/* ../../userpanel/

openssl genrsa -out lms.pem 2048
openssl rsa -in lms.pem -pubout > lms.pub