<?php




namespace Bastion;

interface Config {

    /**
     * @return bool
     */
    public function isDryRun();

    /**
     * @return string
     */
    public function getOutputDirectory();

    /**
     * @return string|null
     */
    public function getAccessToken();

    /**
     * @return mixed
     */
    public function getRepoList();

    public function getSiteName();

    public function getBucketName();

    public function getS3Key();

    public function getS3Secret();

    public function getS3Region();

    public function getRestrictionClass();

    public function getUploaderClass();

    public function setUploaderClass($uploaderClass);
}