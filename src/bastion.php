<?php

use ConsoleKit\Console;

require_once(realpath(__DIR__).'/bootstrap.php');

$config = getConfig();

$console = new Console();

if ($config == null) {
    generateConfig($console);
}



echo "echo processing artifacts\n";

$ignoreList = realpath(__DIR__)."/../ignoreList.txt";
$usingList = realpath(__DIR__)."/../usingList.txt";

getArtifacts($ignoreList, $usingList, $config);


//syncArtifactBuild(AWS_SERVICES_KEY, AWS_SERVICES_SECRET, $allowedIPAddresses);

//$artifactFetcher->processRemoveList("./removeList.txt");
//$console = new Console(array(
//                           'hello' => 'HelloWorldCommand',
//                           'SayHelloCommand',
//                           'SayCommand',
//                           'progress'
//                       ));
//
//$console->run();


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