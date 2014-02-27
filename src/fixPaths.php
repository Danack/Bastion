<?php

require_once(realpath(__DIR__).'/../vendor/autoload.php');

$accessToken = false;

$included = @include_once(realpath(__DIR__)."/../../config.php");

if($included == false) {
    echo "Failed to include config.php from the directory below Bastion. i.e. outside of the git root.\n";
    echo "\n";
    echo "Please copy the config file from 'copy-below-root', and put your credentials in it.";
    exit(-1);
}
//php vendor/bin/satis   zipsOutput 

//require __DIR__.'/../src/bootstrap.php';

//use Composer\Satis\Console\Application;
//use Symfony\Component\Console\Input\ArrayInput;
////
//$application = new Application();
//
//$arguments = array(
//    'command'       => 'build',
//    'file'          => 'satis-zips.json',
//    'output-dir'    => './zipsOutput/laravel',
//    '-vv'           => true,
//);
//
////Create the commands
//$input = new ArrayInput($arguments);
//
//$application->run($input);


$src = "./zipsOutput/packages.json";
$text = file_get_contents($src);
$text = str_replace("/documents/projects/github/Bastion/Bastion/zipsOutput", "http://satis.basereality.com", $text);
file_put_contents($src, $text);

$src = "./zipsOutput/index.html";
$text = file_get_contents($src);
$text = str_replace("/documents/projects/github/Bastion/Bastion/zipsOutput", "", $text);
file_put_contents($src, $text);


