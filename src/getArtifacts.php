<?php

use Intahwebz\Bastion\ArtifactFetcher;

require_once(realpath(__DIR__).'/../vendor/autoload.php');

$accessToken = false;
$included = include_once(realpath(__DIR__)."/../../config.php");

if($included == false) {
    echo "Failed to include config.php from the directory below Bastion. i.e. outside of the git root.\n";
    echo "\n";
    echo "Please copy the config file from 'copy-below-root', and put your credentials in it.\n";
    exit(-1);
}

if ($accessToken == false) {
    echo "No Github access token, downloads will be rate-limited.\n";
}

if ((isset($listOfRepositories) == false) || (is_array($listOfRepositories) == false)) {
    echo "\$listOfRepositories is not or is not an array.";
    exit(0);
}


$artifactFetcher = new ArtifactFetcher(
    $ignoreList,
    $usingList,
    $outputDirectory,
    $accessToken
);

$artifactFetcher->downloadZipArtifacts(
    $listOfRepositories
);
