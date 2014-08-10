<?php

use ConsoleKit\Console;
use Composer\Satis\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

require_once(realpath(__DIR__).'/bootstrap.php');

if (!ini_get('allow_url_fopen')) {
    echo "allow_url_fopen is not enabled, things will break.";
}

$config = getConfig();

$console = new Console();

if ($config == null) {
    generateConfig($console);
    return;
}

if (count($config->getRepoList()) == 0) {
    echo "Repo list is empty - nothing to process. Please list some ";
    exit(0);
}

//Step 0 - 
$injector = createInjector($config);

$artifactFetcher = $injector->make('Bastion\ArtifactFetcher');

//$artifactFetcher->processRemoveList($config->getZipsDirectory()."/removeList.txt");

$filename = realpath(dirname(__FILE__).'/../');
$filename .= '/satis-zips.json';

writeSatisJsonFile($filename, $config);


//Step 1 - download everything
$injector->execute('getArtifacts',[':listOfRepositories' => $config->getRepoList()]);


//Step 2 - Run satis to build the site files and description of the packages.
$absolutePath = dirname(realpath($config->getOutputDirectory()));

//Create the command
$input = new ArrayInput([
    'command' => 'build',
    'file' => $filename,
    'output-dir' => $absolutePath.'/zipsOutput'
]);
//Create the application and run it with the commands
$application = new Application();
$application->run($input);

//Step 3 - fix the broken paths
fixPaths($config->getOutputDirectory(), $config->getSiteName());

//Step 4 upload all the things
$injector->execute('syncArtifactBuild');



/*

    / * *
     * @param $removeListName
     * @throws BastionException
     * /
public function processRemoveList($removeListName) {

}


*/