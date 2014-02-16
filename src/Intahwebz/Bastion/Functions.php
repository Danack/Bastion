<?php

namespace Intahwebz\Bastion {

    class Functions {
        static public function load(){}
    }
}

namespace {

function downloadZipArtifacts($repos, $ignoreListFile, $destDirectory, $accessToken = null) {
    $zipsDirectory = $destDirectory;

    foreach ($repos as $repo) {
        cacheRepo($repo, $zipsDirectory, $ignoreListFile, $accessToken);
    }
}

function getTagsForRepo($repo, $accessToken) {
    $tagPath = "https://api.github.com/repos/".$repo."/tags";

    if ($accessToken) {
        $tagPath .= '?access_token='.$accessToken;
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
        throw new \Exception("Failed to get tag list for repo ".$repo);
    }

    $tagContentArray = json_decode($fileContents, true);

    return $tagContentArray;
}

function cacheRepo($repo, $zipsDirectory, $ignoreListFile, $accessToken) {
    $tagsForRepo = getTagsForRepo($repo, $accessToken);
    downloadZipBallsForTags($repo, $zipsDirectory, $ignoreListFile, $accessToken, $tagsForRepo);
}

function downloadZipBallsForTags($repo, $zipsDirectory, $ignoreListFile, $accessToken, $tagContentArray) {
    $ignoreList = file($ignoreListFile, FILE_IGNORE_NEW_LINES);

    foreach ($tagContentArray as $tagContent) {
        $tagName = $tagContent['name'];
        $zendReleasePrefix = 'release-';
        if (strpos($tagName, $zendReleasePrefix) === 0) {
            $tagName = substr($tagName, strlen($zendReleasePrefix));
        }

        $repoTagName = str_replace("/", "_", $repo).'_'.$tagName;

        $zipFilename = $zipsDirectory.'/'.$repoTagName.'.zip';

        if (in_array($repoTagName, $ignoreList) == true) {
            //echo "Repo $repo with tag $tagName is in the ignore list, skipping.\n";
            continue;
        }

        if (file_exists($zipFilename) == false) {
            $url = $tagContent['zipball_url'];

            if ($accessToken) {
                $url .= '?access_token='.$accessToken;
            }

            downloadFile($url, $zipFilename);
        }

        try {
            modifyComposerJsonInZip($zipFilename, $tagName);            
        }
        catch (\Intahwebz\Bastion\InvalidComposerFile $icf) {
            echo "Failed modify composer.json for repo $repo with tag $tagName . It probably lacks a valid composer.json file.";
            markFileToSkip($repoTagName);
            @unlink($zipFilename);
        }
    }
}

function downloadFile($url, $filename) {
    echo "Downloading $url to  $filename \n";
    $fp = fopen($filename, 'w');
    $ch = curl_init($url);
    $header = array();
    $header[] = 'User-Agent: Danack-SatisFactory';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    if ($status != 200) {
        throw new \Exception("Failed to download file from url $url");
    }
    
}


function modifyComposerJsonInZip($zipFilename, $tag) {

    $zip = new ZipArchive;

    if ($zip->open($zipFilename) === TRUE) {
        $shortestIndex = -1;
        $shortestIndexLength = -1;
        $fileToReplace = null;

        for( $i = 0; $i < $zip->numFiles; $i++ ){
            $stat = $zip->statIndex( $i );

            if (basename($stat['name']) == 'composer.json'){
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

        //echo "Found the file at $shortestIndex\n";
        $contents = $zip->getFromIndex($shortestIndex);

        $modifiedContents = addVersionStringToJson($contents, $tag);

        if ($modifiedContents) {
            echo "Adding version tag $tag to file $zipFilename.\n";
            $zip->deleteName($fileToReplace);    //Delete the old...
            $zip->addFromString($fileToReplace, $modifiedContents); //Write the new...
        }

        $zip->close();//And write back to the filesystem.
    }
    else {
        echo 'failed to open';
        throw new \Exception("Failed to open $zipFilename to check version info.");
    }
}


function addVersionStringToJson($contents, $version){

    $contentsInfo = false;
    
    try {
        $contentsInfo = json_decode($contents, true);
    }
    catch (\Exception $e) {
        throw new \Intahwebz\Bastion\InvalidComposerFile("JSON decode failed. \n");
    }
    
    if (is_array($contentsInfo) == false) {
        throw new \Intahwebz\Bastion\InvalidComposerFile("Json_decode failed for contents [".$contents."] - non-utf8 characters present?");
    }
    
    if (array_key_exists('version', $contentsInfo) == false) {
        $contentsInfo['version'] = $version;
        return json_encode($contentsInfo);
    }

    return false;
}

function markFileToSkip($zipFilename) {
    file_put_contents("ignoreList.txt", $zipFilename."\n", FILE_APPEND);
}



}