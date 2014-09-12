<?php

use ConsoleKit\Console;
use Composer\Satis\Console\Application as SatisApplication;
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

//Step 0 - bootstrap.
$injector = createInjector($config);
$artifactFetcher = $injector->make('Bastion\ArtifactFetcher');

//$artifactFetcher->processRemoveList($config->getZipsDirectory()."/removeList.txt");

$filename = realpath(dirname(__FILE__).'/../');
$filename .= '/satis-zips.json';

//Step 1 - download everything
$injector->execute('getArtifacts',[':listOfRepositories' => $config->getRepoList()]);


writeSatisJsonFile($filename, $config);
//Step 2 - Run satis to build the site files and description of the packages.
echo "Finished downloading, running Satis".PHP_EOL;
$absolutePath = dirname(realpath($config->getOutputDirectory()));

$satisApplication = new SatisApplication();
$appDefinition = $satisApplication->getDefinition();
//Create the command
$input = new ArrayInput([
    'command' => 'build',
    'file' => $filename,
    'output-dir' => $absolutePath.'/zipsOutput'
]);


//Create the application and run it with the commands
$satisApplication->setAutoExit(false);
$satisApplication->run($input);

echo "step 3 fix paths.";
//Step 3 - fix the broken paths. Satis has a bug 
fixPaths($config->getOutputDirectory(), 'http://'.$config->getSiteName());

//Step 4 upload all the things
if ($config->getUploaderClass()) {
    $injector->execute('syncArtifactBuild');
}
