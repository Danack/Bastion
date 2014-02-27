<?php

require_once "../config.php";
require_once "./vendor/autoload.php";

use Intahwebz\Bastion\S3Sync;

use Aws\S3\S3Client;

$s3Client = S3Client::factory(array(
    'key'    => AWS_SERVICES_KEY,
    'secret' => AWS_SERVICES_SECRET,
    'region' => 'eu-west-1'
));

$sync = new S3Sync( 
    "satis.basereality.com",
    $allowedIPAddresses,
    $s3Client
);

$sync->putFile("./zipsOutput/index.html", 'zips/index.html');

//$text = file_get_contents("./zipsOutput/packages.json");
//$text = str_replace("/documents/projects/github/Bastion/Bastion/zipsOutput", "", $text);
//file_put_contents("./zipsOutput/packages.json", $text);

$sync->putFile("./zipsOutput/packages.json", 'zips/packages.json');
$sync->putFile("./zipsOutput/packages.json", 'zips/packages.json');
$sync->syncDirectory("./zipsOutput/packages/", "zips/packages");
$sync->updateACL();
