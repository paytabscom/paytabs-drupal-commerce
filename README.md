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
* You can install this module via Composer, or
* clone it from drupal.org Git repo, or
* Download the module from D.O and install it the usual way:
   - Place it in the /modules/contrib directory
   - Go to 'Extend' as an administrator, and
   - Enable the module

* Install Via Composer:
  - composer require paytabscom/drupal_commerce

* Install Via Github Repo Link:
  - https://github.com/paytabscom/drupal_commerce

CONFIGURATION
-------------
* Create new PayTabs payment gateway
  Administration > Commerce > Configuration > Payment gateways > Add payment gateway
  Provide the following settings:
  - Merchant Profile id.
  - Server key.
  - Merchant region.
  - Order Complete status

SCREENSHOTS
-------------


![Configuration Page](/../main/src/images/configuration page.jpg?raw=true "Configuration Page")

![Checkout Page](/../main/src/images/checkout page.jpg?raw=true "Checkout Page")

![Payment Page](/../main/src/images/payment page.jpg?raw=true "Payment Page")

![return Page](/../main/src/images/return page.jpg?raw=true "return Page")

![order status Page](/../main/src/images/order status dashboard.jpg?raw=true "order status Page")

![payment result Page](/../main/src/images/payment result page dashboard.jpg?raw=true "payment result Page")
