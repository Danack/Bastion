<?php

namespace Bastion;

use Aws\S3\S3Client;


class S3Sync implements Uploader {

    /**
     * @var S3Client
     */
    private $s3Client;
    
    /** @var  S3ACLGenerator */
    private $s3ACLGenerator;

    private $bucket;


    function __construct($bucket, S3ACLGenerator $s3ACLGenerator, S3Client $s3Client) {
        $this->bucket = $bucket;
        $this->s3ACLGenerator = $s3ACLGenerator;
        $this->s3Client = $s3Client;
    }

    /**
     * 
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
     * Upload a string to a file
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
        
        // We can poll the object until it is accessible
        $this->s3Client->waitUntilObjectExists(array(
            'Bucket' => $this->bucket,
            'Key'    => $destFile
        ));
    }

    /**
     * Synchronise a directory, i.e. making all files that exist in the local
     * directory to the remote directory
     * @param $srcDirectory
     * @param $destDirectory
     */
    function syncDirectory($srcDirectory, $destDirectory) {
        
        if (strlen($destDirectory)) {
            //Make sure folder exists, if it is not the root of the r
            $params = array(
                'Bucket' => $this->bucket,
                'Key' => $destDirectory.'/',
                'Body' => '',
                'Metadata' => array()
            );
            $this->s3Client->putObject($params);
        }

        $this->s3Client->uploadDirectory(
            $srcDirectory,
            $this->bucket,
            $destDirectory
        );
    }

    /**
     * 
     */
    function finishProcessing() {
        $allowCondition = $this->s3ACLGenerator->generateConditionBlock();
        //@TODO - aren't those numbers meant to be unique?
        $policy = '{
            "Id": "Policy1392421300612",
            "Statement": [
                {
                    "Sid": "Stmt1392421295029",
                    "Action": [
                        "s3:GetObject"
                    ],
                    "Effect": "Allow",
                    "Resource": "arn:aws:s3:::'.$this->bucket.'/*",
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
