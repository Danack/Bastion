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

$sync->putFile("./zipsOutput/index.html", 'zips/index.html');

$text = file_get_contents("./zipsOutput/packages.json");
$text = str_replace("/documents/projects/github/Bastion/Bastion/zipsOutput", "", $text);
file_put_contents("./zipsOutput/packages.json", $text);

//$sync->putFile("./zipsOutput/packages.json", 'zips/packages.json');
//
//$sync->putDataAsFile($text, 'zips/packages.json');
//$sync->syncDirectory("./zipsOutput/packages/", "zips/packages");
//$sync->updateACL();