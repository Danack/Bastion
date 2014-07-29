<?php

use ConsoleKit\Console;

require_once(realpath(__DIR__).'/bootstrap.php');

$config = getConfig();

$console = new Console();

if ($config == null) {
    generateConfig($console);
    return;
}


//echo "echo processing artifacts\n";
$injector = createInjector($config);

//$artifactFetcher->processRemoveList("./removeList.txt");

//Step 1 - download everything
$injector->execute('getArtifacts',[':listOfRepositories' => $config->getRepoList()]);

//Step 2 - fix the broken paths
fixPaths($outputDirectory, $siteURL);

//Step 3 upload all the things
$injector->execute('syncArtifactBuild');



/*

    / * *
     * @param $removeListName
     * @throws BastionException
     * /
public function processRemoveList($removeListName) {
    $lines = @file($removeListName);
    if ($lines === false) {
        throw new BastionException("Could not open remove list with file name $removeListName");
    }

    $zipDirectoryRealPath = realpath($this->config->getZipsDirectory());

    foreach ($lines as $line) {
        $repoTagName = trim($line);
        if (strlen($repoTagName) == 0) {
            continue;
        }

        $zipFilename = $this->getZipFilename($repoTagName);
        echo "Delete package $repoTagName which has zipfilename ".$zipFilename."\n";

        $fileToRemoveRealPath = realpath(dirname($zipFilename));

        if (strpos($fileToRemoveRealPath, $zipDirectoryRealPath) !== 0) {
            printf(
                "Skipping removing %s it is outside of the current zipsDirectory %s".PHP_EOL,
                $repoTagName,
                $this->config->getZipsDirectory()
            );
            continue;
        }

        //remove the line from using list
        //Delete the archive
        $this->repoInfo->addRepoTagToIgnoreList($repoTagName);
    }
}


*/