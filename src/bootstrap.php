<?php

use Aws\S3\S3Client;

use Amp\Artax\Client as ArtaxClient;
use Amp\Reactor;
use Amp\ReactorFactory;
use Bastion\ArtifactFetcher;
use Bastion\Config;
use Bastion\Uploader;
use Bastion\BastionException;
use Composer\Satis\Console\Application as SatisApplication;
use Danack\Console\Application;
use Danack\Console\Input\InputInterface;
use Danack\Console\Output\OutputInterface;
use Bastion\BastionArtaxClient;
use Danack\Console\Command\Command;
use Danack\Console\Input\InputArgument;
use Bastion\Config\DialogueConfigGenerator;
use Bastion\OutputLogger;

require_once(realpath(__DIR__).'/../vendor/autoload.php');

define('CONFIG_FILE_NAME', 'bastionConfig.php');


function formatKeyNames($params) {
    $newParams = [];
    foreach ($params as $key => $value) {
        $newParams[':'.$key] = $value;
    }

    return $newParams;
}


/**
 * Read the config file from either the level above the root directory, or the root
 * directory of Bastion.
 * I strongly recommend not putting S3 keys in a place where they can accidentally 
 * be committed to a repo.
 * @return \Bastion\Config\Config
 */
function getConfig() {
    $configLocations = [
        realpath(__DIR__)."/../../".CONFIG_FILE_NAME, //outside of project
        realpath(__DIR__)."/../".CONFIG_FILE_NAME, //project root
    ];

    foreach ($configLocations as $configLocation) {
        if (file_exists($configLocation) == true) {
            $config = include_once $configLocation;
            return $config;
        }
    }

    return null;
}


/**
 * Either read the config file, or take the user through a series of
 * questions to generate the config.
 * @param InputInterface $input
 * @param OutputInterface $output
 */
function getConfigOrGenerate(InputInterface $input, OutputInterface $output, Application $console, DialogueConfigGenerator $dialogueGenerator) {
    
    $logo = <<< END
______              _    _               
| ___ \            | |  (_)              
| |_/ /  __ _  ___ | |_  _   ___   _ __  
| ___ \ / _` |/ __|| __|| | / _ \ | '_ \ 
| |_/ /| (_| |\__ \| |_ | || (_) || | | |
\____/  \__,_||___/ \__||_| \___/ |_| |_|
                                         
                                         

END;


    
    $config = getConfig();
    if ($config == null) {
        $output->writeln('');
        $output->writeln($logo);


        $output->writeln("Config file could not be loaded either from the current directory or the parent directory. Bastion will now take you through the config generation process. Alternatively you can copy the file 'bastionConfig.placeholder.php' to 'bastionConfig.php' and edit the values directly.".PHP_EOL);

        if (!$input->isInteractive()) {
            throw new \Bastion\BastionException("Config cannot be generated in a non-interactive shell.");
        }
        
        //$questionHelper = new QuestionHelper();
        //$questionHelper->setHelperSet($console->getHelperSet());
        //$configGenerator = new Bastion\Config\DialogueGenerator($input, $output, $questionHelper);
        $configGenerator = $dialogueGenerator;
        
        $configGenerator->generateConfig();

        try {
            $config = getConfig();
        }
        catch (\Exception $e) {
            echo "Generated config throws an exception: ".$e->getMessage();
            exit(0);
        }
    }
    
    return $config;
}





/**
 * Get all of the artifacts (packages) that are listed in the config, modify the 
 * composer.json file in them to include the version number so that they can be understand by Satis.
 * Also generate the input file Satis needs to be able to run.
 * @param $listOfRepositories
 * @param Config $config
 */
function getArtifacts(
    Bastion\Config $config,
    ArtifactFetcher $artifactFetcher,
    Reactor $reactor, $satisFilename) {

    if (count($config->getRepoList()) == 0) {
        echo "Repo list is empty - nothing to process. Please list some ";
        return;
    }

    $artifactFetcher->downloadZipArtifacts(
        $config->getRepoList()
    );
    
    $reactor->run();

    writeSatisJsonFile($satisFilename, $config);
}

/**
 * Satis generates the file paths with an absolute path so that the links are borked e.g.
 * http://localhost:8000/documents/projects/github/Bastion/Bastion/zipsOutput/packages/zendframework_Component_ZendFilter_2.2.5.zip
 * instead of 
 * http://localhost:8000/packages/zendframework_Component_ZendFilter_2.2.5.zip
 * 
 * This function corrects the paths to allow the files to be deployed to a different server than where they were built, or be accessed by a different path e.g. symlink.
 * 
 * See https://github.com/composer/satis/issues/122 
 * @param $outputDirectory
 * @param $siteURL
 */
function fixPaths($outputDirectory, $siteURL) {

    $absolutePath = realpath($outputDirectory);

    fixPathsInFile($outputDirectory."/packages.json", $absolutePath, $siteURL);
    fixPathsInFile($outputDirectory."/index.html", $absolutePath, $siteURL);

    $includeFiles = glob($outputDirectory.'/include/*.json');
    
    foreach($includeFiles as $includeFile) {
        fixPathsInFile($includeFile, $absolutePath, $siteURL);
    }
}


/**
 * Satis does not generate path names correctly, so we fix them.
 * @param $filename
 * @param $absolutePath
 * @param $siteURL
 */
function fixPathsInFile($filename, $absolutePath, $siteURL) {
    $text = @file_get_contents($filename);

    if ($text === false) {
        throw new LogicException("Failed to open file $filename. Presumably it wasn't built?"
        );
    }

    $text = str_replace($absolutePath, $siteURL, $text);
    file_put_contents($filename, $text);
}

/**
 * @param Uploader $uploader
 */
function syncArtifactBuild(Config $config, Uploader $uploader) {
    $outputDirectory = $config->getOutputDirectory();
    $uploader->putFile($outputDirectory."/404.html", '404.html');
    $uploader->putFile($outputDirectory."/index.html", 'index.html');
    $uploader->putFile($outputDirectory."/packages.json", 'packages.json');
    $uploader->syncDirectory($outputDirectory."/include/", "include");
    $uploader->syncDirectory($outputDirectory."/packages/", "packages");
    $uploader->finishProcessing();
    echo "Upload complete.\n";
}


/**
 * Debug method for converting an \Artax\Request to a curl 
 * command line request. 
 * @param \Amp\Artax\Request $request
 * @return string
 */
function toCurl(Amp\Artax\Request $request) {
    $output = '';
    $output .= 'curl -X '.$request->getMethod()." \\\n";

    foreach ($request->getAllHeaders() as  $header => $values) {
        foreach ($values as $value) {
            $output .= "-H \"$header: $value\" \\\n";
        }
    }

    $body = $request->getBody();
    if (strlen($body)) {
        $output .= "-d '".addslashes($body)."' ";
    }

    $output .= '"'.$request->getUri().'"';
    $output .= "\n";

    return $output;
}



/**
 * @param $filePath
 * @return bool
 * @throws \Exception
 */
function ensureDirectoryExists($filePath) {
    $directoryName = dirname($filePath);
    @mkdir($directoryName, 0755, true);

    return file_exists($directoryName);
}

/**
 * Delegate function for creating an S3Client object
 * @param Config $config
 * @return S3Client
 */
function createS3Client(Config $config) {
    if (strlen($config->getS3Key()) == 0) {
        echo "S3Key is zero length - S3 upload unlikely to work.";
    }
    
    if (strlen($config->getS3Secret()) == 0) {
        echo "S3Secret is zero length - S3 upload unlikely to work.";
    }

    if (strlen($config->getS3Region()) == 0) {
        echo "S3Region is zero length - S3 creating bucket is unlikely to work.";
    }

    $s3Client = S3Client::factory([
        'key' => $config->getS3Key(),
        'secret' => $config->getS3Secret(),
        'region' => $config->getS3Region()
    ]);

    $s3Client->getConfig()->set('curl.options', array(CURLOPT_VERBOSE => true));

    return $s3Client;
}


/**
 * Create the injector - this is the heart of where the application turns the 
 * configuration into an actual usable DIC.
 * @param Config $config
 * @return \Auryn\Provider
 */
function createInjector(Config $config = null) {
    $injector = new \Auryn\Provider();
    
    $classAliases = [
        //'GithubService\GithubService' => 'GithubService\GithubArtaxService\GithubArtaxService',

        'GithubService\GithubService' => 'GithubService\GithubArtaxService\GithubService',
        
        'Bastion\RepoInfo' => 'Bastion\FileStoredRepoInfo',
        'Bastion\RPM\BuildConfigProvider' => 'Bastion\RPM\RootFileBuildConfigProvider',
    ];



    //Share all the classes that should only be singletons
    $injector->share('Bastion\RepoInfo');
    $injector->share('Amp\Reactor');
    $injector->share('Bastion\Progress');
    $injector->share('Artax\Client');

    //Define scalar values
    $injector->define('Bastion\S3ACLRestrictByIPGenerator', [':allowedIPAddresses' => []]);

    $injector->define(
        'Bastion\FileStoredRepoInfo',
        [
            ':ignoreListFilename' => realpath(__DIR__)."/../ignoreList.txt",
            ':usingListFilename' => realpath(__DIR__)."/../usingList.txt"
        ]
    );

    $injector->define(
        'GithubService\GithubArtaxService\GithubArtaxService',
        [':userAgent' => "Danack/Bastion"]
    );

    
    $injector->define(
        'GithubService\GithubArtaxService\GithubService',
        [':userAgent' => "Danack/Bastion"]
    );
    
    
    
    //Delegate all the things!
    $injector->delegate('Aws\S3\S3Client', 'createS3Client');
    $injector->delegate('Amp\Reactor', function() {
            return (new ReactorFactory)->select();
    });


    $createArtaxClient = function (
        Amp\Reactor $reactor, 
        \Bastion\Progress $progress,
        OutputLogger $output) {
        //This extends the client, to be able to put a watch on each of the
        //Promises that Artax returns
        $client = new BastionArtaxClient($output, $progress, $reactor);
        $client->setOption(ArtaxClient::OP_MS_KEEP_ALIVE_TIMEOUT, 3);
        $client->setOption(ArtaxClient::OP_HOST_CONNECTION_LIMIT, 4);
        //$client->setOption(ArtaxClient::OP_HOST_CONNECTION_LIMIT, -1);


        // This might be a good idea...
        // $this->client->setOption(\Artax\Parser::OP_MAX_BODY_BYTES, 20 * 1024 * 1024);
        return $client;
    };

    $injector->delegate('Amp\Artax\Client', $createArtaxClient);

    $filename = realpath(dirname(__FILE__).'/../');
    $filename .= '/satis-zips.json';
    $injector->defineParam('satisFilename', $filename);

    if ($config == null) {
        $classAliases['ArtaxServiceBuilder\ResponseCache']  = 'ArtaxServiceBuilder\ResponseCache\NullResponseCache';
    }
    else {

        $classAliases['ArtaxServiceBuilder\ResponseCache']  = 'ArtaxServiceBuilder\ResponseCache\FileResponseCache';
        
        if ($aclGeneratorClass = $config->getRestrictionClass()) {
            $classAliases['Bastion\S3ACLGenerator'] = $aclGeneratorClass;
        }

        if ($uploaderClass = $config->getUploaderClass()) {
            $classAliases['Bastion\Uploader'] = 'Bastion\S3Sync';
        }

        $injector->alias('Bastion\Config', get_class($config));
        $injector->share($config);
        $injector->define('Bastion\S3Sync', [':bucket' => $config->getBucketName()]);
        $injector->define(
            'ArtaxServiceBuilder\ResponseCache\FileResponseCache',
            [':cacheDirectory' => $config->getCacheDirectory()]
        );

        $injector->delegate(
            'Bastion\URLFetcher',
            function (\Amp\Artax\Client $client) use ($config) {
                return new \Bastion\URLFetcher($client, $config->getAccessToken());
            }
        );
    }

    foreach ($classAliases as $class => $alias) {
        $injector->alias($class, $alias);
    }

    $injector->share($injector); //YOLO service locator

    return $injector;
}


/**
 * @throws BastionException
 */
function processRemoveList() {
    echo "processRemoveList not implemented yet.";
    return;
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

/**
 * Write a satis config file, that just tells Satis where to look for
 * artifacts.
 * @TODO - shift this to in memory? We could use 
 * https://github.com/thornag/php-vfs to remove any 'temp' writing,
 * but the current system works...so could be overkill.
 * @param Config $config
 */
function writeSatisJsonFile($filename, Config $config) {
    $path = $config->getOutputDirectory();
    $absolutePath = realpath($path).'/packages/';
    $text = <<< END

{
    "name": "Bastion package repository",
    "description": "This file is auto-generated to allow Satis to run.",
    "homepage": "http://satis.basereality.com/zipsOutput",
    "repositories": [
        {
            "packagist": false
        },
        {
            "type": "artifact",
            "url": "$absolutePath"
        }
    ],
    "require-all": true
}
    
END;

    $written = @file_put_contents($filename, $text);

    if ($written === false) {
        throw new LogicException("Failed to write $filename");
    }
}

/**
 * Run satis to build the site files and description of the packages.
 * @param Config $config
 * @param $satisFilename
 * @throws Exception
 */
function runSatis(\Bastion\Config $config, $satisFilename) {
    echo "Finished downloading, running Satis".PHP_EOL;
    $absolutePath = dirname(realpath($config->getOutputDirectory()));

    $satisApplication = new SatisApplication();

    //Create the command
    $input = new \Symfony\Component\Console\Input\ArrayInput([
        'command' => 'build',
        'file' => $satisFilename,
        'output-dir' => $absolutePath.'/zipsOutput'
    ]);

    //Create the application and run it with the commands
    $satisApplication->setAutoExit(false);
    $satisApplication->run($input);

    //Step 3 - fix the broken paths. Satis has a bug 
    fixPaths($config->getOutputDirectory(), 'http://'.$config->getSiteName());
}

/**
 * Runs all the steps needed to build a bastion repository and upload it
 * @param \Auryn\Provider $injector
 */
function runCompleteRepoProcess(\Auryn\Provider $injector) {
    $injector->execute('getArtifacts');
    $injector->execute('runSatis');
    $injector->execute('syncArtifactBuild');
}

/**
 * Creates a console application with all of the commands attached.
 * @return Application
 */
function createConsole() {
    $rpmCommand = new Command('rpmdir', ['Bastion\RPMProcess', 'packageSingleDirectory']);
    $rpmCommand->addArgument('directory', InputArgument::REQUIRED, "The directory containing the composer'd project to build into an RPM.");
//$uploadCommand->addOption('dir', null, InputArgument::OPTIONAL, 'Which directory to upload from', './');
    $rpmCommand->setDescription("Build an RPM from an directory that contains all the files of a project. Allows for faster testing than having to re-tag, and download zip files repeatedly.");

    $uploadCommand = new Command('upload', 'syncArtifactBuild');
    $uploadCommand->setDescription("Uploads all of the files for the satis repository to the upload destination.");

    $downloadCommand = new Command('download', 'getArtifacts');
    $downloadCommand->setDescription("Downloads all of the packages listed as repositories in your config, and processes them ready to be used in a satis repository.");

    $satisCommand = new Command('satis', 'runSatis');
    $satisCommand->setDescription("Runs satis to build the website files for the satis repository. Does not download or upload files.");


    $allCommand = new Command('bastion', 'runCompleteRepoProcess');
    $allCommand->setDescription("Runs the 'download', 'satis' and 'upload' commands, i.e. everything required to build your own satis repository and upload it to a remote site.");

    $console = new Application("Bastion", "1.0.0");
    $console->add($allCommand);
    $console->add($rpmCommand);
    $console->add($uploadCommand);
    $console->add($downloadCommand);
    $console->add($satisCommand);

    

////Step 0 - bootstrap.
//$artifactFetcher = $injector->make('Bastion\ArtifactFetcher');
////$artifactFetcher->processRemoveList($config->getZipsDirectory()."/removeList.txt");
//$injector->execute(['Bastion\RPMProcess', 'process']);
//    $params = [
//        //':sourceDirectory' => "/documents/projects/github/Bastion/Bastion/temp/intahwebz-master"
//        ':sourceDirectory' => "/home/github/Bastion/Bastion/temp/intahwebz-master"
//    ];
//
//    $injector->execute(['Bastion\RPMProcess', 'packageSingleDirectory'], $params);


//Add Install script 

//Finish config file generator



    return $console;
}