Commerce PayTabs PT2

Description
-----------
This module provides integration with the PayTabs payment gateway.

CONTENTS OF THIS FILE
---------------------
* Introduction
* Requirements
* Installation
* Configuration

INTRODUCTION
------------
This project integrates PayTabs online payments into
the Drupal Commerce payment and checkout systems.

REQUIREMENTS
------------
This module requires no external dependencies.
But make sure to enable the 'Telephone' core module.

INSTALLATION
------------

* Install Via Composer:
  - composer require paytabscom/paytabs-drupal-commerce

* Install Via Github Repo Link:
  - https://github.com/paytabscom/paytabs-drupal-commerce
  - extract the downloaded file and Place it in the /modules/contrib directory with folder name paytabs_drupal_commerce

- Go to 'Extend' as an administrator, and Enable the module

CONFIGURATION
-------------
* Create new PayTabs payment gateway
  Administration > Commerce > Configuration > Payment gateways > Add payment gateway
  Provide the following settings:
  - Merchant Profile id.
  - Server key.
  - Merchant region.
  - Order Complete status
  
* Make sure to install telephone module and enable it
  - go to config / people / profile types/ manage fields / add new field
  - add phone number field with name phone.
  
* Make sure that your website currency is as same as your currency in payTabs profile

SCREENSHOTS
-------------


![Configuration Page](/../main/src/images/configuration%20page.jpg?raw=true "Configuration Page")

![Checkout Page](/../main/src/images/checkout%20page.jpg?raw=true "Checkout Page")

![Payment Page](/../main/src/images/payment%20page.jpg?raw=true "Payment Page")

![return Page](/../main/src/images/return%20page.jpg?raw=true "return Page")

![order status Page](/../main/src/images/order%20status%20dashboard.jpg?raw=true "order status Page")

![payment result Page](/../main/src/images/payment%20result%20page%20dashboard.jpg?raw=true "payment result Page")
