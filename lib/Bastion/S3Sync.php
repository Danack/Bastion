<?php

namespace Bastion;

use Aws\S3\S3Client;

class S3Sync {

    private $s3Client;

    private $bucket;
    
    private $allowedIPAddresses;

    function __construct($bucket, $allowedIPAddresses, S3Client $s3Client) {
        $this->allowedIPAddresses = $allowedIPAddresses;
        $this->bucket = $bucket;
        $this->s3Client = $s3Client;
    }

    /**
     * @param $sourceFile
     * @param $destFile
     */
    function putFile($sourceFile, $destFile) {

        $result = $this->s3Client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destFile,
            'SourceFile' => $sourceFile,
            'Metadata'   => array(
            )
        ));
        //Content-Type header by passing a ContentType
        
        if (!$result) {
            throw new \RuntimeException("Failed to upload file $sourceFile to S3 file $destFile");
        }

        // We can poll the object until it is accessible
        $this->s3Client->waitUntilObjectExists(array(
            'Bucket' => $this->bucket,
            'Key'    => $destFile
        ));
    }

    /**
     * @param $sourceText
     * @param $destFile
     */
    function putDataAsFile($sourceText, $destFile) {

        $result = $this->s3Client->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $destFile,
            'Body'       => $sourceText,
            'Metadata'   => array(
            )
        ));

        if (!$result) {
            throw new \RuntimeException("Failed to upload text to S3 file $destFile");
        }

        //Content-Type header by passing a ContentType

        // We can poll the object until it is accessible
        $this->s3Client->waitUntilObjectExists(array(
            'Bucket' => $this->bucket,
            'Key'    => $destFile
        ));
    }

    /**
     * @param $srcDirectory
     * @param $destDirectory
     */
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

    /**
     * @param $restrictByIP
     */
    function updateACL($restrictByIP) {
        $generateCondition = function ($ipAddress) { 
            return sprintf('"IpAddress": {
                                "aws:SourceIp": "%s"
                            }', $ipAddress);
        };

        $conditions = array_map($generateCondition, $this->allowedIPAddresses);
        
        $allowCondition = implode(', ', $conditions);
        
        $allowCondition = '';
        
        if ($restrictByIP) {
            //Well this is ugly - this should whole function should
            //be refactored to separate classes to represent the conditions
            //But as this is a proof of concept...not today.
            $allowCondition = '"Condition": {
            '.$allowCondition.'
            },';
        }
        
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
                    '.$allowCondition.'
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
