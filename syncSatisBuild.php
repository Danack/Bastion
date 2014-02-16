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


$sync->putFile("./satisOutput/index.html", 'satis-public/index.html');
$sync->putFile("./satisOutput/packages.json", 'satis-public/packages.json');
$sync->syncDirectory("./satisOutput/packages/", "satis-public/packages");
$sync->updateACL();
