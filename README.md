# LMS BillTech payments plugin

## Description
This plugin provides integration with BillTech platform.
It injects payment details into mail headers and a button/link to a new invoice e-mail notification.

## Installation
* Put contents of this repository in *plugins/BillTech* inside LMS root directory
* Enter *plugins/BillTech* directory
* Run `chmod 700 install.sh`
* Run `install.sh`
* Enable the plugin in *configuration -> plugins*
* Add payment button to your *new invoice* template - insert `%billtech_btn` placeholder for the button.

## Configuration
* *install.sh* generates a pair of keys (*lms.pem* and *lms.pub*). Send ONLY lms.pub to BillTech via email <michal(at)billtech.pl>. 
* Create new configuration entry `billtech.private_key`. Paste contents of *lms.pem* as a value.
* Create new configuration entry `billtech.isp_id`. Use your *isp_id* provided by BillTech <michal(at)billtech.pl>
* Create new configuration entry `billtech.payment_url`. Use your *payment_url* provided by BillTech <michal(at)billtech.pl>