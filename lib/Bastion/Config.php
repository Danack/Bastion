<?php


namespace Bastion;


class Config {

    private $zipsDirectory;
    private $dryRun;
    private $accessToken;
    private $repoList;
    private $restrictionClass;
    private $bucketName;
    private $s3Key;
    private $s3Secret;
    private $s3Region;
    private $uploaderClass;
    
    function __construct(
        $zipsDirectory, 
        $dryRun, 
        $accessToken, 
        $repoList, 
        $restrictionClass, 
        $bucketName,
        $s3Key,
        $s3Secret,
        $s3Region,
        $uploaderClass
    ) {

        if (strpos($zipsDirectory, '/') === 0) {
            //Absolute path
            $this->zipsDirectory = $zipsDirectory;
        }
        else {
            //Relative path, correct it to be relative the root directory of this project.
            $bastionRootPath = dirname(__FILE__).'/../../';
            $this->zipsDirectory = realpath($bastionRootPath.$zipsDirectory);
        }
        
        
        $this->dryRun = $dryRun;
        $this->accessToken = $accessToken;
        $this->repoList = $repoList;
        $this->restrictionClass = $restrictionClass;
        $this->bucketName = $bucketName;
        $this->s3Key = $s3Key;
        $this->s3Secret = $s3Secret;
        $this->s3Region = $s3Region;
    }
    
    /**
     * @return bool
     */
    public function isDryRun() {
        return $this->dryRun;
    }

    /**
     * @return string
     */
    public function getOutputDirectory() {
        return $this->zipsDirectory;
    }

    /**
     * @return string|null
     */
    public function getAccessToken() {
        return $this->accessToken;
    }

    /**
     * @return mixed
     */
    public function getRepoList() {
        return $this->repoList;
    }
    
    public function getSiteName() {
        return $this->bucketName;   
    }
    
    public function getBucketName() {
        return $this->bucketName;
    }
    
    public function getS3Key() {
        return $this->s3Key;
    }

    public function getS3Secret() {
        return $this->s3Secret;
    }

    public function getS3Region() {
        return $this->s3Region;
    }
    
    public function getRestrictionClass() {
        return $this->restrictionClass;
    }
    
    public function getUploaderClass() {
        return $this->uploaderClass;
    }

    public function setUploaderClass($uploaderClass) {
        $this->uploaderClass = $uploaderClass;
    }
    
    
    
}

 