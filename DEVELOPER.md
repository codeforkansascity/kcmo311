

Setup
=====

Database
--------

1) Create a database with user and password
2) Copy bin/example.config.php to config.php
3) Edit config.php and enter database information
4) Initialize database with doc/create.sql





Slim Middleware Extra
=====================

After you run composer you need to rename 

   vendor/slim/extras/Slim/Extras/Middleware/Jsonp.php

to

   vendor/slim/extras/Slim/Extras/Middleware/JSONPMiddleware.php


