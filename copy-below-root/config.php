<?php


// Current ip address
$allowedIPAddresses = array();

$accessToken = false;

define('AWS_SERVICES_KEY', '12345');
define('AWS_SERVICES_SECRET', '12345');


$ignoreList = "./ignoreList.txt";
$usingList = "./usingList.txt";
$outputDirectory = "./zipsOutput/packages";