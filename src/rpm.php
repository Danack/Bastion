<?php


use Composer\Json\JsonValidationException;

use Composer\Console\Application as ComposerApplication;
use Composer\Command\UpdateCommand;
use Symfony\Component\Console\Input\ArrayInput;

use Bastion\RPM\RootFileBuildConfigProvider;

require_once(realpath(__DIR__).'/bootstrap.php');

if (true) {
    $buildDir = tempdir(__DIR__.'/../temp', 'BuildRPM');
}
else {
    $buildDir = "/documents/projects/github/Bastion/Bastion/temp/BuildRPM_2014_09_07_18_40_36_540ca6a4446f0";
}

$artifactFilename = __DIR__."/../temp/intahwebz-master.zip";
$repoDirectory = __DIR__.'/../repo';

$version = '1.0.0';

$buildConfigProvider = new RootFileBuildConfigProvider();


rpmArtifact($artifactFilename, $buildDir, $repoDirectory, $version, $buildConfigProvider);
