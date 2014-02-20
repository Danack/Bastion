<?php

namespace Intahwebz\Bastion;


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
            throw new \Exception("Failed to get tag list for repo " . $repo);
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

            $repoTagName = str_replace("/", "_", $repo) . '_' . $tagName;

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
                $this->markFileToBeingUsed($repoTagName);
            } catch (\Intahwebz\Bastion\InvalidComposerFile $icf) {
                echo "Failed modify composer.json for repo $repo with tag $tagName . It probably lacks a valid composer.json file.";
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
        $fp = fopen($filename, 'w');
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
    }

    /**
     * @param $zipFilename
     * @param $tag
     * @throws InvalidComposerFile
     * @throws \Exception
     */
    function modifyComposerJsonInZip($zipFilename, $tag) {

        $zip = new \ZipArchive;

        if ($zip->open($zipFilename) !== TRUE) {
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


        $modifiedContents = $this->addVersionStringToJson($contents, $tag);

        if (!isset($config['name'])) {
            throw new \Intahwebz\Bastion\InvalidComposerFile('Zipfile $zipFilename no name defined in its composer.json');
        }

        if ($modifiedContents) {
            echo "Adding version tag $tag to file $zipFilename.\n";
            $zip->deleteName($fileToReplace); //Delete the old...
            $zip->addFromString($fileToReplace, $modifiedContents); //Write the new...
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
            $contentsInfo['version'] = $version;
            return json_encode($contentsInfo);
        }

        return false;
    }

    /**
     * Add a filename to the list being used. This allows for easier removal later
     * with the exact key name.
     * @param $zipFilename
     */
    function markFileToBeingUsed($zipFilename) {
        $usingList = file_get_contents($this->usingListFilename);

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