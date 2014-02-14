<?php

namespace Intahwebz\Bastion;

use Aws\S3\S3Client;

class S3Sync {

    private $client;

    private $bucket;
    
    private $allowedIP;

    function __construct($key, $secret, $bucket, $allowedIP) {

        $this->allowedIP = $allowedIP;
        $this->bucket = $bucket;
        $this->client = S3Client::factory(array(
            'key'    => $key,
            'secret' => $secret,
            'region' => 'eu-west-1' //todo - hard coded
        ));
    }

    function putFile($sourceFile, $destFile) {

        $result = $this->client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destFile,
            'SourceFile' => $sourceFile,
            'Metadata'   => array(
            )
        ));
        //Content-Type header by passing a ContentType

        // We can poll the object until it is accessible
        $this->client->waitUntilObjectExists(array(
            'Bucket' => $this->bucket,
            'Key'    => $destFile
        ));
    }

    function putDataAsFile($sourceText, $destFile) {

        $result = $this->client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destFile,
            'Body'       => $sourceText,
            'Metadata'   => array(
            )
        ));
        //Content-Type header by passing a ContentType

        // We can poll the object until it is accessible
        $this->client->waitUntilObjectExists(array(
            'Bucket' => $this->bucket,
            'Key'    => $destFile
        ));
    }

    function syncDirectory($srcDirectory, $destDirectory) {

        //Make sure folder exists
        $this->client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destDirectory.'/',
            'Body'       => '',
            'Metadata'   => array(
            )
        ));

        $this->client->uploadDirectory(
            $srcDirectory,
            $this->bucket,
            $destDirectory
        );
    }

    function updateACL() {

        $policy = '{
            "Id": "Policy1392421300612",
          "Statement": [
            {
                "Sid": "Stmt1392421295029",
              "Action": [
                "s3:GetObject"
            ],
              "Effect": "Allow",
              "Resource": "arn:aws:s3:::satis.basereality.com/*",
              "Condition": {
                "IpAddress": {
                    "aws:SourceIp": "'.$this->allowedIP.'"
                }
              },
              "Principal": {
                "AWS": [
                    "*"
                ]
              }
            }
          ]
        }';

        $this->client->putBucketPolicy(array(
            'Bucket' => $this->bucket,
            'Policy' => $policy
        ));
    }
}
