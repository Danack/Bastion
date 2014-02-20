#!/bin/bash


php getArtifacts.php

#why the heck does this even attempt to run scripts?
php vendor/bin/satis build --no-scripts satis-zips.json zipsOutput -vv 
php syncArtifactBuild.php

# php -S localhost:8000 -t zipsOutput/
