<?php

require_once(realpath(__DIR__).'/../vendor/autoload.php');

$accessToken = false;

$included = @include_once(realpath(__DIR__)."/../../config.php");

if($included == false) {
    echo "Failed to include config.php from the directory below Bastion. i.e. outside of the git root.\n";
    echo "\n";
    echo "Please copy the config file from 'copy-below-root', and put your credentials in it.";
    exit(-1);
}


//this is hard coded - to change this would also require changing it in the bash script.
$outputDirectory = "./zipsOutput/packages";
$absolutePath = dirname(realpath($outputDirectory));

$src = "./zipsOutput/packages.json";
$text = file_get_contents($src);
$text = str_replace($absolutePath, $siteURL, $text);
file_put_contents($src, $text);

$src = "./zipsOutput/index.html";
$text = file_get_contents($src);
$text = str_replace($absolutePath, "", $text);
file_put_contents($src, $text);


