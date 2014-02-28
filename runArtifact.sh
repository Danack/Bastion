#!/bin/bash

set -e

php ./src/getArtifacts.php

php vendor/bin/satis build satis-zips.json zipsOutput -vv

php ./src/fixPaths.php

php ./src/syncArtifactBuild.php


# If you wish to test your satis repository locally, and
# don't have a local webserver setup, you can test it with 
# PHP's built-in webserver  
# php -S localhost:80 -t zipsOutput/
