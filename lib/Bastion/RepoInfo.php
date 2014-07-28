<?php


namespace Bastion;


interface RepoInfo {

    /**
     * 
     */
    function getRepoList();

    /**
     * 
     */
    function addRepoTagToUsingList($repoTagName);

    /**
     * 
     */
    function addRepoTagToIgnoreList($zipFilename);

    /**
     * @return bool
     */
    function isInIgnoreList($zipFilename);
}

 