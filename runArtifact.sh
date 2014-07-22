#!/bin/bash

set -e

/usr/local/bin/php -d allow_url_fopen=On ./src/getArtifacts.php

/usr/local/bin/php -d allow_url_fopen=On vendor/bin/satis build satis-zips.json zipsOutput -vv

/usr/local/bin/php -d allow_url_fopen=On ./src/fixPaths.php

/usr/local/bin/php -d allow_url_fopen=On ./src/syncArtifactBuild.php


# If you wish to test your satis repository locally, and
# don't have a local webserver setup, you can test it with 
# PHP's built-in webserver  
# php -S localhost:80 -t zipsOutput/
