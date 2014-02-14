<?php

require_once "../config.php";
require_once "./vendor/autoload.php";

use Intahwebz\Bastion\S3Sync;

$sync = new S3Sync(
    AWS_SERVICES_KEY, 
    AWS_SERVICES_SECRET, 
    "satis.basereality.com",
    $allowedIP
);

$packagesJson = file_get_contents("./output/packages.json");

//$packagesJson = str_replace(
//    "https://satis-public.basereality.com",
//
//    $packagesJson
//);


$sync->putFile("./output/index.html", 'satis-public/index.html');
$sync->putDataAsFile($packagesJson, 'satis-public/packages.json');
$sync->syncDirectory("./output/packages/", "satis-public/packages");
$sync->updateACL();
