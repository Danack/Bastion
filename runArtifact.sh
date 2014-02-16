#!/bin/bash


php getArtifacts.php
php vendor/bin/satis build satis-zips.json zipsOutput -vv
php syncArtifactBuild.php

# php -S localhost:8000 -t zipsOutput/
