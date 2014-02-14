#!/bin/bash

php vendor/bin/satis build ../satis-public.json output
php syncPublic.php