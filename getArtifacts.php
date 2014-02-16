<?php

require_once('./vendor/autoload.php');

\Intahwebz\Bastion\Functions::load();

$accessToken = false;

include_once("../config.php");

if ($accessToken == false) {
    echo "No Github access token, downloads will be rate-limited.\n";
}

require_once("../repos.config.php");

downloadZipArtifacts($listOfRepositories, "../ignoreList.txt", "./zipsOutput/packages", $accessToken);