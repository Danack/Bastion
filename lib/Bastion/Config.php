<?php


namespace Bastion;


class Config {

    private $zipsDirectory;
    
    private $dryRun;

    private $accessToken;
    
    private $repoList;
    
    function __construct($zipsDirectory, $dryRun, $accessToken, $repoList) {
        $this->zipsDirectory = $zipsDirectory;
        $this->dryRun = $dryRun;
        $this->accessToken = $accessToken;
        $this->repoList = $repoList;
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
    public function getZipsDirectory() {
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
}

 