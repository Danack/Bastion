<?php


namespace Bastion;


/**
 * Class FileStoredRepoInfo
 * 
 * Stores information about what repos are being used, and which are being ignored
 * 
 * @package Bastion
 */
class FileStoredRepoInfo implements RepoInfo {

    private $ignoreListFilename;
    private $usingListFilename;
    private $ignoreList;
    
    function __construct($ignoreListFilename, $usingListFilename) {
        $this->ignoreListFilename = $ignoreListFilename;
        $this->usingListFilename = $usingListFilename;
        //The using list gets reset everytime, and only reflects the most recent run
        file_put_contents($usingListFilename, '');

        $this->ignoreList = file($this->ignoreListFilename, FILE_IGNORE_NEW_LINES);
    }
    
    /**
     * @return mixed
     */
    function getRepoList() {
        // TODO: Implement getRepoList() method.
    }

    /**
     * Add a filename to the list being used. This allows for easier removal later
     * with the exact key name.
     * @param $zipFilename
     */
    function addRepoTagToUsingList($zipFilename) {
        $usingList = @file_get_contents($this->usingListFilename);

        if (strpos($usingList, $zipFilename) === false) {
            file_put_contents($this->usingListFilename, $zipFilename . "\n", FILE_APPEND);
        }
    }

    /**
     * Mark a filename to skip. This is used to avoid repeatedly downloading bad versions,
     * or to exlcude unwanted version completely.
     * @param $zipFilename
     * @param $reason
     */
    function addRepoTagToIgnoreList($zipFilename, $reason) {
        $this->ignoreList[] = $zipFilename;

        $newString = $zipFilename . "\n    ". $reason."\n";
        file_put_contents($this->ignoreListFilename, $newString, FILE_APPEND);
    }

    /**
     * @param $zipFilename
     * @return bool
     */
    function isInIgnoreList($zipFilename) {
        return in_array($zipFilename, $this->ignoreList);
    }


}

 