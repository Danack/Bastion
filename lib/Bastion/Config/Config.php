<?php


namespace Bastion\Config;


use Bastion\BastionException;

class Config implements \Bastion\Config {

    private $outputDirectory;
    private $tempDirectory;
    private $rpmDirectory;
    private $dryRun;
    private $accessToken;
    private $repoList;
    private $rpmList;
    private $restrictionClass;
    private $bucketName;
    private $s3Key;
    private $s3Secret;
    private $s3Region;
    private $uploaderClass;


    /**
     * Normalize the directory name and make sure it exists.
     * @param $directory
     * @return string
     * @throws BastionException
     */
    private function normalizeDirectory($directory) {
        if (strpos($directory, '/') === 0) {
            //Absolute path
            return $directory;
        }

        //Relative path, correct it to be relative the root directory of this project.
        $bastionRootPath = dirname(__FILE__).'/../../../';
        //@TODO - make directory
        @mkdir($bastionRootPath.$directory);
        $actualPath = realpath($bastionRootPath.$directory);
        if (!$actualPath) {
            throw new BastionException(
                sprintf(
                    "Directory `%s` doesn't exist and could not be created.",
                    $bastionRootPath.$directory
                )
            );
        }

        return $actualPath;
    }

    
    function __construct(
        $outputDirectory,
        $tempDirectory,
        $rpmDirectory,
        $accessToken, 
        $repoList,
        $rpmList,
        $restrictionClass, 
        $bucketName,
        $s3Key,
        $s3Secret,
        $s3Region,
        $uploaderClass
    ) {
        $this->outputDirectory = $this->normalizeDirectory($outputDirectory);
        $this->tempDirectory = $this->normalizeDirectory($tempDirectory);
        $this->rpmDirectory = $this->normalizeDirectory($rpmDirectory);
        $this->accessToken = $accessToken;
        $this->repoList = $repoList;
        $this->rpmList = $rpmList;
        $this->restrictionClass = $restrictionClass;
        $this->bucketName = $bucketName;
        $this->s3Key = $s3Key;
        $this->s3Secret = $s3Secret;
        $this->s3Region = $s3Region;
        $this->uploaderClass = $uploaderClass;
    }
    
    /**
     * @return bool
     */
    public function isDryRun() {
        return $this->dryRun;
    }

    /**
     * @param $isDryRun
     * @return bool
     */
    public function setIsDryRun($isDryRun) {
        return $this->dryRun = $isDryRun;
    }
    
    /**
     * @return string
     */
    public function getOutputDirectory() {
        return $this->outputDirectory;
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

    /**
     * @return mixed
     */
    public function getRpmList() {
        return $this->rpmList;
    }

    /**
     * @param mixed $rpmList
     */
    public function setRpmList($rpmList) {
        $this->rpmList = $rpmList;
    }

    public function getCacheDirectory() {
        return $this->tempDirectory.'/cache';
    }
    
    public function getRPMOutputDirectory() {
        return __DIR__.'/../../../repo';
    }
    
    public function getRPMBuildDirectory() {
        return $this->tempDirectory.'/build';
    }

    public function getTempDirectory() {
        return $this->tempDirectory;
    }

    
}

 