<?php

namespace Bastion;

use Composer\Package\Version\VersionParser;
use GithubService\GithubService;
use Artax\Request;
use Artax\Response;


/**
 * Remove everything before the first digit in a string, if the string contains digits.
 * @param $text
 * @return string
 */
function subFirstDigit($text) {
    preg_match('/^\D*(?=\d)/', $text, $matches, PREG_OFFSET_CAPTURE);

    if (isset($matches[0])) {
        return substr($text, strlen($matches[0][0]));
    }

    return $text;
}



class ArtifactFetcher {

    /**
     * @var \GithubService\GithubService
     */
    private $githubAPI;

    /**
     * @var RepoInfo
     */
    private $repoInfo;

    /**
     * @var \Bastion\URLFetcher
     */
    private $urlFetcher;

    /**
     * @var \Bastion\Config
     */
    private $config;

    /**
     * @var Progress
     */
    private $progress;
    
    /** @var Array of uris that have already been/or are being procesed. Used
     *  to prevent crappy pagination from make the same resources being downloaded 
     * multiple times. 
     */
    private $processedURIs = array();
    
    
    function __construct(
        GithubService $githubAPI,
        \Bastion\URLFetcher $fileFetcher,
        \Bastion\RepoInfo $repoInfo,
        Config $config,
        \Bastion\Progress $progress
        
    ) {
        $this->githubAPI = $githubAPI;
        $this->repoInfo = $repoInfo;
        $this->urlFetcher = $fileFetcher;
        $this->config = $config;
        $this->progress = $progress;
    }

    /**
     * @param $repoTagName string The repository name appended by '_'.$tag e.g. "swiftmailer/swiftmailer/swiftmailer_swiftmailer_v4.1.3"
     * @return string
     */
    function getZipFilename($repoTagName) {
        $zipFilename = $this->config->getOutputDirectory().'/packages/'.$repoTagName.'.zip';

        return $zipFilename;
    }

    function abort() {
        //Quitting is hard.
        exit(0);
    }

    /**
     * @param $errorMessage
     */
    function abortProcess($errorMessage) {
        $this->progress->displayStatus($errorMessage);
    }


    /**
     * @param \Exception $error
     * @param Response $response
     */
    function processErrorResponse($action, \Exception $error, Response $response) {
        
        $status = $response->getStatus();
        $errorMessage = false;
        
        switch($status) {

            case (404): {
                $errorMessage = "404 during $action. This either means the repo doesn't exist or you don't have permission to access it. Github give 404 for un-authorized access valid resources to prevent information leakage.";
                break;
            }
        }

        if ($errorMessage == true) {
            $this->progress->displayStatus($errorMessage, 5);
        }
        else {
            $this->progress->displayStatus("Unknown error in addRepoToProcess callback: ".$error->getMessage(
                ).'. Exception class is '.get_class($error), 5
            );

            if ($error instanceof \ArtaxServiceBuilder\BadResponseException) {
                var_dump($error->getRequest()->getAllHeaders());
                var_dump($error->getRequest()->getBody());
            }

            if ($response) {
                var_dump($response);
            }
        }


        return true;
    }

    /**
     * @param $repos
     * @throws BastionException
     */
    function downloadZipArtifacts($repos) {
        foreach ($repos as $repo) {
            $this->addRepoToProcess($repo);
        }
    }
    
    private function alreadyProcessed(Request $request) {
        $uri = $request->getUri();
        if (array_key_exists($uri, $this->processedURIs) == true) {
            return true;
        }

        $this->processedURIs[$uri] = true;

        return false;
    }
    

    /**
     * @param $owner
     * @param $reponame
     * @return callable
     */
    function getProcessRepoTagsCallback($owner, $reponame) {
        $callback = function(
            \Exception $error = null, 
            \GithubService\Model\RepoTags $repoTags = null,
            \Artax\Response $response) use($owner, $reponame) {

            if ($error) {
                $this->processErrorResponse("Fetching repo tags for ".$owner."/".$reponame, $error, $response);
                $this->abort();
                return;
            }

            $this->processRepoTags($owner, $reponame, $repoTags);
            $this->processShittyPagination($owner, $reponame, $repoTags);
        };

        return $callback;
    }
    

    /**
     * @param $repo
     * @throws BastionException
     */
    function addRepoToProcess($repo) {
        $firstSlashPosition = strpos($repo, '/');
        if ($firstSlashPosition === false) {
            throw new BastionException("Could not parse `$repo` into owner and repo name ");
        }

        $owner = substr($repo, 0, $firstSlashPosition);
        $reponame = substr($repo, $firstSlashPosition + 1);
        $callback = $this->getProcessRepoTagsCallback($owner, $reponame);
        $command = $this->githubAPI->listRepoTags($this->config->getAccessToken(), $owner, $reponame);

        $request = $command->createRequest();
        
        if ($this->alreadyProcessed($request) == true) {
            return;
        }

        $command->dispatchAsync($request, $callback);
    }

    /**
     * Process Githubs pagination. You would have thought that paging through a resource
     * would involve modifying page_off, or start_resource - but no, we have to use a different
     * API call to a straight URL that allegedly contains the paging information.
     * @param $owner
     * @param $reponame
     * @param \GithubService\Model\RepoTags $repoTags
     */
    function processShittyPagination($owner, $reponame, \GithubService\Model\RepoTags $repoTags) {
        if ($repoTags->pager) {
            $newPages = $repoTags->pager->getAllKnownPages();
            foreach ($newPages as $pageURL) {
                $command = $this->githubAPI->listRepoTagsPaginate($this->config->getAccessToken(), $pageURL);
                $callback = $this->getProcessRepoTagsCallback($owner, $reponame);

                $request = $command->createRequest();

                if ($this->alreadyProcessed($request) == true) {
                    return;
                }

                $command->dispatchAsync($request, $callback);
            }
        }
    }
    

    /**
     * @param $owner
     * @param $repo
     * @param \GithubService\Model\RepoTags $repoTags
     */
    function processRepoTags($owner, $repo, \GithubService\Model\RepoTags $repoTags) {
        $this->progress->displayStatus("process repo tags owner $owner, repo $repo.");
        foreach ($repoTags->getIterator() as $repoTag) {
            //Check that this is the same as what is being written to ignore list file

            list($repoTagName, ) = $this->normalizeRepoTagName($owner, $repo, $repoTag->name);
            if ($this->repoInfo->isInIgnoreList($repoTagName) == true) {
                continue;
            }
            $this->getRepoArtifact($owner, $repo, $repoTag);
        }
    }

    /**
     * Normalize the repo name to a repo tag name, and a zipfile name so we can be consistent.
     * @param $owner
     * @param $repo
     * @param $tagName
     * @return array
     */
    function normalizeRepoTagName($owner, $repo, $tagName) {
        $zendReleasePrefix = 'release-';
        if (strpos($tagName, $zendReleasePrefix) === 0) {
            $tagName = substr($tagName, strlen($zendReleasePrefix));
        }
        $normalisedRepoName = str_replace("/", "_", $repo);
        //Information is duplicated, to allow us to be aware of the owner and repo name when
        //just looking at the file name without having to check the directory name 
        $repoTagName = $owner.'/'.$normalisedRepoName.'/'.$owner.'_'.$normalisedRepoName.'_' .$tagName;
        $zipFilename = $this->getZipFilename($repoTagName);

        return [$repoTagName, $zipFilename];
    }

    /**
     * Downloads a zipball of a repo artifact, and modifies the composer.json to make sure that
     * it is usable by Composer.
     * @param $owner
     * @param $repo
     * @param \GithubService\Model\RepoTag $repoTag
     * @throws \Exception
     */
    function getRepoArtifact($owner, $repo, \GithubService\Model\RepoTag $repoTag) {
        if ($this->config->isDryRun() == true) {
            return;
        }

        $responseCallback = function(\Exception $error = null, \Artax\Response $response = null) use ($owner, $repo, $repoTag) {

            if ($error) {
                $outputString = "Error in getRepoArtifact ".$error->getMessage();
                $this->progress->displayStatus($outputString);
                return;
            }

            $this->processDownloadedFileResponse($response, $owner, $repo, $repoTag);
        };

        list($repoTagName, $zipFilename) = $this->normalizeRepoTagName($owner, $repo, $repoTag->name);

        if (file_exists($zipFilename) == false) {
            //$outputString .= "Starting download";
            //$this->progress->displayStatus($outputString);
            $this->urlFetcher->downloadFile($repoTag->zipballURL, $responseCallback);
        }
        else {
            //$outputString .= "$zipFilename already exists.";
            //$this->progress->displayStatus($outputString);
            $this->repoInfo->addRepoTagToUsingList($repoTagName);
        }
    }
    

    /**
     * @param \Artax\Response $response
     * @param $owner
     * @param $repo
     * @param $repoTag
     * @throws \Exception
     */
    function processDownloadedFileResponse(\Artax\Response $response, $owner, $repo, $repoTag) {
        list($repoTagName, $zipFilename) = $this->normalizeRepoTagName($owner, $repo, $repoTag->name);

        $status = $response->getStatus();
        if ($status < 200 || $status >= 300) {
            $this->abortProcess("Downloading zipfile $zipFilename from ".$repoTag->zipballURL." did not result in success, actual status ".$status);
            return;
        }

        $body = $response->getBody();

        if (!ensureDirectoryExists($zipFilename)) {
            $this->abortProcess("Directory for $zipFilename does not exist and could not be created.");
            return;
        }

        $tmpfname = tempnam("./tmp/", "download");

        $written = file_put_contents($tmpfname, $body);
        if ($written === false) {
            $this->abortProcess('Could not write downloaded file $repoTag to temp directory');
            return;
        }

        try {
            $this->modifyComposerJsonInZip($tmpfname, $repoTag->name);
        }
        catch (InvalidComposerFileException $icf) {
            $reason = 'InvalidComposerFileException for '.$repo.' '.$repoTag->name.': '.$icf->getMessage();
            //$reason = "Failed to modify composer.json for repo $repo with tag ".$repoTag->name.": ".$icf->getMessage();
            echo $reason."\n";
            $this->repoInfo->addRepoTagToIgnoreList($repoTagName, $reason);
            unlink($tmpfname);
            return;
        }

        $renamed = rename($tmpfname, $zipFilename);
        if ($renamed === false) {
            $this->abortProcess('Could not atomically rename temp file $tmpfname to zipFilename $zipFilename');
            return;
        }
        
        echo "Download complete of $repoTagName".PHP_EOL;

        $this->repoInfo->addRepoTagToUsingList($repoTagName);
    }

    /**
     * Modifies a composer json in a zip file to ensure both the name and version entries are set correctly.
     * @param $zipFilename
     * @param $tag
     * @throws InvalidComposerFileException
     * @throws \Exception
     */
    function modifyComposerJsonInZip($zipFilename, $tag) {
        $zip = new \ZipArchive;
        $result = $zip->open($zipFilename, \ZipArchive::ER_READ);

        if ($result !== TRUE) {
            throw new BastionException("Failed to open $zipFilename to check version info.");
        }

        $shortestIndex = -1;
        $shortestIndexLength = -1;
        $fileToReplace = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if (basename($stat['name']) == 'composer.json') {
                $length = strlen($stat['name']);
                if ($shortestIndex == -1 || $length < $shortestIndexLength) {
                    $shortestIndex = $i;
                    $shortestIndexLength = $length;
                    $fileToReplace = $stat['name'];
                }
            }
        }

        if ($shortestIndex == -1) {
            $zip->close();
            throw InvalidComposerFileException::fromMissingComposer(
                $zipFilename
            );
        }

        $contents = $zip->getFromIndex($shortestIndex);

        try {
            $contentsInfo = json_decode($contents, true);
        }
        catch (\Exception $e) {
            throw InvalidComposerFileException::fromJsonDecodeFailed(
                $zipFilename
            );
        }

        if (!isset($contentsInfo['name'])) {
            throw InvalidComposerFileException::fromMissingName(
                $zipFilename
            );
        }
        
        $modifiedContents = $this->ensureValidVersionIsSet($contentsInfo, $tag);

        if ($modifiedContents) {
            $zip->deleteName($fileToReplace); //Delete the old...
            $zip->addFromString($fileToReplace, json_encode($modifiedContents)); //Write the new...
        }

        $zip->close(); //And write back to the filesystem.
    }

    /**
     * Adds a version entry to an composer info array if there isn't already one present.
     * If one is present, validate it to be a semver comprehensible version string. 
     * @param $contentsInfo
     * @param $version
     * @internal param $contents
     * @return bool|string
     * @throws \Bastion\InvalidComposerFileException If the version entry is already set, but was not parsible by VersionParser
     */
    function ensureValidVersionIsSet($contentsInfo, $version) {
        $versionParser = new VersionParser();
        $version = subFirstDigit($version);

        if (array_key_exists('version', $contentsInfo) == false) {
            try {
                $versionParser->normalize($version);
            }
            catch(\UnexpectedValueException $uve) {
                throw InvalidComposerFileException::fromBadSemver($version);
            }
            
            $contentsInfo['version'] = $version;
            return $contentsInfo;
        }


        try {
            $versionParser->normalize($contentsInfo['version']);
        }
        catch(\UnexpectedValueException $uve) {
            throw InvalidComposerFileException::fromBadNormalizedSemver(
                $contentsInfo['version']
            );
        }

        return false;
    }
}



