#!/bin/bash

set -e

php getArtifacts.php

#why the heck does this even attempt to run scripts?
php vendor/bin/satis build satis-zips.json zipsOutput -vv
 
# --no-scripts

php fixPaths.php

# php syncArtifactBuild.php

# php -S localhost:8000 -t zipsOutput/

# php -S localhost:80 -t zipsOutput/
