<?php


namespace Bastion;


interface RepoInfo {

    /**
     * 
     */
    function getRepoList();

    /**
     * @param $repoTagName
     * @return
     */
    function addRepoTagToUsingList($repoTagName);

    /**
     * @param $zipFilename
     * @param $reason
     * @return
     */
    function addRepoTagToIgnoreList($zipFilename, $reason);

    /**
     * @param $zipFilename
     * @return bool
     */
    function isInIgnoreList($zipFilename);
}

 