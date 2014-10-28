<?php

use Bastion\Config;
use Aws\S3\S3Client;

require_once(realpath(__DIR__).'/../vendor/autoload.php');

if (!ini_get('allow_url_fopen')) {
    echo "allow_url_fopen is not enabled, things will probably break.".PHP_EOL;
}


$s3Key = getenv('S3KEY');
$s3Secret = getenv('S3SECRET');


if ($s3Key === false) {
    echo "S3KEY is not set, cannot sync website".PHP_EOL;
    exit(-1);
}

if ($s3Secret === false) {
    echo "S3SECRET is not set, cannot sync website.".PHP_EOL;
    exit(-1);
}


try {

    $s3Client = S3Client::factory([
       'key' => $s3Key,
       'secret' => $s3Secret,
       'region' => 'eu-west-1'
    ]);

    $s3Client->getConfig()->set('curl.options', array(CURLOPT_VERBOSE => true));
    $aclGenerator = new Bastion\S3ACLNoRestrictionGenerator();
    $s3Sync = new Bastion\S3Sync(
        'www.bastionrpm.com',
        $aclGenerator,
        $s3Client
    );

    $s3Sync->syncDirectory("../doc/build/html", "");
    $s3Sync->finishProcessing();

    exit(0);
}
catch(\Exception $e) {
    echo "Unexpection error running ".__FILE__.": ".$e->getMessage().PHP_EOL;
    exit(-1);
}