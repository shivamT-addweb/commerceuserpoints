README file for Commerce User Points

CONTENTS OF THIS FILE
---------------------
* Introduction
* Requirements
* Installation
* Configuration
* How It Works
* Troubleshooting
* Maintainers

INTRODUCTION
------------

Commerce User Points module provides User Points functionality with Commerce.

REQUIREMENTS
------------
This module requires the following:
* Commerce (and its dependencies) (https://drupal.org/project/commerce)

INSTALLATION
------------
* Download module and put it into modules directory.
* Enable module from /admin/modules.

CONFIGURATION
-------------

* Administration > Commerce > Commerce User Points Config
  - User registration points (Points that user get on account registration)
  - Percentage 
    (User Points return on purchase of product based on configured percentage.)
  - ADVANCED SETTINGS
    - Day (Give special discount on specific day)
  - Threshold value (Threshold limit for user for using when purchase.)

HOW IT WORKS
------------

* General considerations:
  - When user creates account then he/she will gets some predefined points.

* Checkout workflow:
  - At checkout process User points Redeem option show.
  - User can use all usable points.(If thrash hold set then user can not 
  used thrash hold points example:usable points =total points -threshold points)
  - User can also used specific number of points.
  - Final price calculated after points apply. 
    (final price = total price - usable points)

TROUBLESHOOTING
---------------
* No troubleshooting pending for now.

MAINTAINERS
-----------
Current maintainer:
* Jigish Chuhan (jigish) - https://www.drupal.org/u/jigishaddweb

This project has been developed by:
* Addwebsolution - https://www.addwebsolution.com/
