<?php

namespace Intahwebz\Bastion;

use Composer\Package\Version\VersionParser;

function ensureDirectoryExists($filePath) {
    $pathSegments = array();

    $slashPosition = 0;
    $finished = false;

    while ($finished === false) {

        $slashPosition = mb_strpos($filePath, '/', $slashPosition + 1);

        if ($slashPosition === false) {
            $finished = true;
        }
        else {
            $pathSegments[] = mb_substr($filePath, 0, $slashPosition);
        }

        $maxPaths = 20;
        if (count($pathSegments) > $maxPaths) {
            throw new \Exception("Trying to create a directory more than $maxPaths deep. What is wrong with you?");
        }
    }

    foreach ($pathSegments as $segment) {
        if (file_exists($segment) === false) {
            //echo "Had to create directory $segment";
            $result = mkdir($segment);

            if ($result == false) {
                throw new \Exception("Failed to create segment [$segment] in ensureDirectoryExists($filePath).");
            }
        }
    }

    return true;
}


class ArtifactFetcher {

    private $ignoreListFilename;
    private $zipsDirectory;
    private $accessToken;
    
    function __construct($ignoreListFilename, $usingListFilename, $zipsDirectory, $accessToken = null) {
        $this->ignoreListFilename = $ignoreListFilename;
        $this->zipsDirectory = $zipsDirectory;
        $this->accessToken = $accessToken;
        $this->usingListFilename = $usingListFilename;
    }

    /**
     * @param $repos
     * @internal param null $accessToken
     */
    function downloadZipArtifacts($repos) {
        foreach ($repos as $repo) {
            $this->cacheRepo($repo);
        }
    }

    /**
     * @param $repo
     * @throws \Exception
     * @internal param $accessToken
     * @return mixed
     */
    function getTagsForRepo($repo) {
        $tagPath = "https://api.github.com/repos/" . $repo . "/tags";

        if ($this->accessToken) {
            $tagPath .= '?access_token=' . $this->accessToken;
        }

        $ch = curl_init($tagPath);
        $header = array();
        $header[] = 'User-Agent: Danack-SatisFactory';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $fileContents = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status != 200 || (!$fileContents)) {
            echo "try $tagPath \n";
            throw new \Exception("Failed to get tag list for repo ".$repo);
        }

        $tagContentArray = json_decode($fileContents, true);

        return $tagContentArray;
    }

    /**
     * @param $repo
     * @internal param $accessToken
     */
    function cacheRepo($repo) {
        $tagsForRepo = $this->getTagsForRepo($repo);
        $this->downloadZipBallsForTags($repo, $tagsForRepo);
    }


    /**
     * @param $repo
     * @param $tagContentArray
     * @internal param $zipsDirectory
     */
    function downloadZipBallsForTags($repo, $tagContentArray) {
        $ignoreList = file($this->ignoreListFilename, FILE_IGNORE_NEW_LINES);

        foreach ($tagContentArray as $tagContent) {
            $tagName = $tagContent['name'];
            $zendReleasePrefix = 'release-';
            if (strpos($tagName, $zendReleasePrefix) === 0) {
                $tagName = substr($tagName, strlen($zendReleasePrefix));
            }

            //$repoTagName = str_replace("/", "_", $repo) . '_' . $tagName;
            $repoTagName = $repo.'/'.str_replace("/", "_", $repo).'_' .$tagName;

            $zipFilename = $this->zipsDirectory . '/' . $repoTagName . '.zip';

            if (in_array($repoTagName, $ignoreList) == true) {
                //echo "Repo $repo with tag $tagName is in the ignore list, skipping.\n";
                continue;
            }

            if (file_exists($zipFilename) == false) {
                $url = $tagContent['zipball_url'];
                echo "About to download $url to $zipFilename\n";
                if ($this->accessToken) {
                    $url .= '?access_token=' . $this->accessToken;
                }

                $this->downloadFile($url, $zipFilename);
            }

            try {
                $this->modifyComposerJsonInZip($zipFilename, $tagName);
                $this->markFileBeingUsed($repoTagName);
            } catch (\Intahwebz\Bastion\InvalidComposerFile $icf) {
                echo "Failed to modify composer.json for repo $repo with tag $tagName: ".$icf->getMessage()." .\n";
                $this->markFileToSkip($repoTagName);
                @unlink($zipFilename);
            }
        }
    }

    /**
     * @param $url
     * @param $filename
     * @throws \Exception
     */
    function downloadFile($url, $filename) {
        $tmpfname = tempnam("./tmp/", "download");
        
        $fp = fopen($tmpfname, "w");
        
        $ch = curl_init($url);
        $header = array();
        $header[] = 'User-Agent: Danack-SatisFactory';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($status != 200) {
            throw new \Exception("Failed to download file from url $url");
        }

        ensureDirectoryExists($filename);
        
        rename($tmpfname, $filename);
    }

    /**
     * @param $zipFilename
     * @param $tag
     * @throws InvalidComposerFile
     * @throws \Exception
     */
    function modifyComposerJsonInZip($zipFilename, $tag) {

        $zip = new \ZipArchive;
        $result = $zip->open($zipFilename, \ZipArchive::ER_READ);

        if ($result !== TRUE) {
            throw new \Exception("Failed to open $zipFilename to check version info.");
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
            throw new \Intahwebz\Bastion\InvalidComposerFile("Failed to find the composer.json file, delete $zipFilename \n");
        }

        $contents = $zip->getFromIndex($shortestIndex);

        try {
            $contentsInfo = json_decode($contents, true);
        } catch (\Exception $e) {
            throw new \Intahwebz\Bastion\InvalidComposerFile("JSON decode failed. \n");
        }

        if (is_array($contentsInfo) == false) {
            throw new \Intahwebz\Bastion\InvalidComposerFile("Json_decode failed for contents [" . $contents . "] - non-utf8 characters present?");
        }

        if (!isset($contentsInfo['name'])) {
            throw new \Intahwebz\Bastion\InvalidComposerFile("Zipfile $zipFilename has no name defined in its composer.json");
        }
        
        $modifiedContents = $this->addVersionStringToJson($contentsInfo, $tag);

        if ($modifiedContents) {
            $zip->deleteName($fileToReplace); //Delete the old...
            $zip->addFromString($fileToReplace, json_encode($modifiedContents)); //Write the new...
        }

        $zip->close(); //And write back to the filesystem.
    }

    /**
     * @param $contentsInfo
     * @param $version
     * @internal param $contents
     * @return bool|string
     */
    function addVersionStringToJson($contentsInfo, $version) {
        if (array_key_exists('version', $contentsInfo) == false) {
            echo "Adding version tag $version.\n";
            $contentsInfo['version'] = $version;
            return $contentsInfo;
        }

        $versionParser = new VersionParser();

        try {
            $versionParser->normalize($contentsInfo['version']);
        }
        catch(\UnexpectedValueException $uve) {
            throw new \Intahwebz\Bastion\InvalidComposerFile("Version ".$contentsInfo['version']." isn't a usable version name.");
        }

        return false;
    }

    /**
     * Add a filename to the list being used. This allows for easier removal later
     * with the exact key name.
     * @param $zipFilename
     */
    function markFileBeingUsed($zipFilename) {
        $usingList = @file_get_contents($this->usingListFilename);

        if (strpos($usingList, $zipFilename) === false) {
            file_put_contents($this->usingListFilename, $zipFilename . "\n", FILE_APPEND);
        }
    }
    
    /**
     * Mark a filename to skip. This is used to avoid repeatedly downloading bad versions,
     * or to exlcude unwanted version completely.
     * @param $zipFilename
     */
    function markFileToSkip($zipFilename) {
        file_put_contents($this->ignoreListFilename, $zipFilename . "\n", FILE_APPEND);
    }
}