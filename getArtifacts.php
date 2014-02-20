<?php

require_once('./vendor/autoload.php');

$accessToken = false;

include_once("../config.php");

if ($accessToken == false) {
    echo "No Github access token, downloads will be rate-limited.\n";
}

require_once("../repos.laravel.config.php");

use Intahwebz\Bastion\ArtifactFetcher;

$artifactFetcher = new ArtifactFetcher(
    "../ignoreList.txt",
    "../usingList.txt",
    "./zipsOutput/packages",
    $accessToken
);

$artifactFetcher->downloadZipArtifacts(
    $listOfRepositories
);