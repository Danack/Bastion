#!/bin/bash

php vendor/bin/satis build ../satis-public.json satisOutput -vv
php syncSatisBuild.php


# php -S localhost:8000 -t output/
