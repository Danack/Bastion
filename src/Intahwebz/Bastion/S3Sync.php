<?php

namespace Intahwebz\Bastion;

use Aws\S3\S3Client;

class S3Sync {

    private $s3Client;

    private $bucket;
    
    private $allowedIP;

    function __construct($bucket, $allowedIP, S3Client $s3Client) {
        $this->allowedIP = $allowedIP;
        $this->bucket = $bucket;
        $this->s3Client = $s3Client;
    }

    function putFile($sourceFile, $destFile) {

        $result = $this->s3Client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destFile,
            'SourceFile' => $sourceFile,
            'Metadata'   => array(
            )
        ));
        //Content-Type header by passing a ContentType
        
        //TODO - check for okay-ness
        var_dump($result);

        // We can poll the object until it is accessible
        $this->s3Client->waitUntilObjectExists(array(
            'Bucket' => $this->bucket,
            'Key'    => $destFile
        ));
    }

    function putDataAsFile($sourceText, $destFile) {

        $result = $this->s3Client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destFile,
            'Body'       => $sourceText,
            'Metadata'   => array(
            )
        ));

        //todo check for okayness
        var_dump($result);
        
        //Content-Type header by passing a ContentType

        // We can poll the object until it is accessible
        $this->s3Client->waitUntilObjectExists(array(
            'Bucket' => $this->bucket,
            'Key'    => $destFile
        ));
    }

    function syncDirectory($srcDirectory, $destDirectory) {

        //Make sure folder exists
        $this->s3Client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destDirectory.'/',
            'Body'       => '',
            'Metadata'   => array(
            )
        ));

        $this->s3Client->uploadDirectory(
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

        $this->s3Client->putBucketPolicy(array(
            'Bucket' => $this->bucket,
            'Policy' => $policy
        ));
    }
}
