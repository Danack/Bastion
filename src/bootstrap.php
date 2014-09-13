<?php

use Aws\S3\S3Client;
use Bastion\ArtifactFetcher;
use Artax\Client as ArtaxClient;
use Alert\Reactor;
use Alert\ReactorFactory;
use ConsoleKit\Console;
use ConsoleKit\Widgets\Dialog;

use Bastion\Config;
use Bastion\Uploader;
use Bastion\BastionException;
use Bastion\RPM\RPMProjectConfig;
use Bastion\RPM\BuildConfigProvider;
use Bastion\RPM\RPMConfigException;
use Bastion\RPM\SpecBuilder;

use Composer\Json\JsonValidationException;
use Composer\Console\Application as ComposerApplication;
use Symfony\Component\Console\Input\ArrayInput;

use Artax\Cookie\CookieJar;
use Artax\HttpSocketPool;
use Acesync\Encryptor;
use Artax\WriterFactory;


require_once(realpath(__DIR__).'/../vendor/autoload.php');

define('CONFIG_FILE_NAME', 'bastionConfig.php');


/**
 * Class DebugAsyncClient
 */
class DebugClient extends ArtaxClient {

    private $progressDisplay;
    
    public function __construct(
        \Bastion\Progress $progressDisplay,
        Reactor $reactor = null,
        CookieJar $cookieJar = null,
        HttpSocketPool $socketPool = null,
        Encryptor $encryptor = null,
        WriterFactory $writerFactory = null) {
        parent::__construct($reactor, $cookieJar, $socketPool, $encryptor, $writerFactory);
        
        $this->progressDisplay = $progressDisplay;
    }
    
    /**
     * @param $uriOrRequest
     * @param array $options
     * @return \After\Promise
     */
    public function request($uriOrRequest, array $options = []) {

        $displayText = "Making request: ";

        if (is_string($uriOrRequest)) {
            $displayText .= "string: ". $uriOrRequest;
        }
        else if ($uriOrRequest instanceof \Artax\Request) {
            $displayText .= "Request uri: ".$uriOrRequest->getUri();
            //echo "\n".toCurl($uriOrRequest);
        }
        else {
            $displayText .= "class ".get_class($uriOrRequest);
        }
        
        $this->progressDisplay->displayStatus($displayText, 1);

        $watchCallback = $this->progressDisplay->getWatcher($uriOrRequest);
        $promise = parent::request($uriOrRequest);
        $progress = new \Artax\Progress($watchCallback);
        $promise->watch($progress);
        
        return $promise;
    }
}


/**
 * @param $configDirectory
 * @param $token
 * @param $zipsDirectory
 */
function writeConfig(
    $configDirectory, 
    $token,
    $zipsDirectory,
    $s3Key,
    $s3Secret,
    $s3Region,
    $domainName,
    $uploaderClass,
    $restrictionClass
    ) {
    $filename = realpath(__DIR__).'/../'.$configDirectory.'/'.CONFIG_FILE_NAME;

    $tokenString = 'null';
    if ($token) {
        $tokenString = "'$token'";
    }
    
    $configBody = <<< END
<?php

// The directory the zip files will be downloaded to and where the
// Satis repo files will be generated.
\$zipsDirectory = '$zipsDirectory';

// The Github access token. Technically Bastion can work without this being set,
// however the Github has a much lower rate limit for unsigned requests.
\$githubAccessToken = $tokenString;

// The S3 key - used if the S3 uploader is enabled
\$s3Key = '$s3Key';

// The S3 secret - used if the S3 uploader is enabled
\$s3Secret = '$s3Secret';

// The S3 region the bucket will be created in if it doesn't already exist. Note
// buckets cannot be moved once created.
\$s3Region = '$s3Region';
\$domainName = '$domainName';

// Uploader class should be one of 
// null - skip uploading
// 'Bastion\S3Sync' - the included S3 uploader.
// alternative you can specify any class that implements the 
// Bastion\Uploader interface if you want to add your own.
\$uploaderClass = '$uploaderClass';

//If S3 is chosen as the uploader, the restriction class will be
//applied to restrict who can access the files
\$restrictionClass = '$restrictionClass';

//The list of github repos that you want your Bastion repo to provide.
//They should be listed as the Github "owner/repo" not the packagist name
\$repoList = [
    //"Behat/Mink",
];

//The config file must return a config object.
return new Config(
    \$zipsDirectory, \$dryRun, 
    \$githubAccessToken, \$repoList, 
    \$restrictionClass, \$bucketName,
    \$s3Key, \$s3Secret,
    \$s3Region,
    \$uploaderClass
);
    

END;

    $written = file_put_contents($filename, $configBody);
    
    if ($written === false) {
        echo "Failed config to filename $filename ".PHP_EOL;
        exit(0);
    }

    $filename = realpath($filename);
    
    echo "Config file created. Please edit $filename to put your required repos in \$repoList".PHP_EOL;
}


/**
 * @param Dialog $dialog
 * @return bool|string
 */
function askForRegion(Dialog $dialog) {
    
    $regions = [
        'us-east-1',
        'us-west-1',
        'us-west-2',
        'eu-west-1',
        'ap-northeast-1',
        'ap-southeast-1',
        'ap-southeast-2',
        'sa-east-1',
        'cn-north-1',
        'us-gov-west-1',
    ];

    echo "Please choose a region for the S3 bucket to be create in. This only has any effect if the bucket doesn't already exist.".PHP_EOL;
    
    $count = 1;
    $regionOptions = [];
    foreach ($regions as $region) {
        echo "$count:) $region".PHP_EOL;
        
        $regionOptions[] = "".$count."";
        $count++;
    }

    $regionOption = $dialog->option(
        'Please enter a digit',
        $regionOptions,
        'p',
        "Please enter a digit that corresponds to one of the regions listed."
    );
    
    return $regionOption; 
}

/**
 * @param Dialog $dialog
 * @return null|string
 */
function askForS3Key(Dialog $dialog) {
    echo "Please enter the AWS key for S3 to be able to upload files.".PHP_EOL;

    $enteredS3Token = $dialog->ask('Please enter your S3 token:');
    if (strlen(trim($enteredS3Token)) < 8) {
        echo "That doesn't look like a token dude.".PHP_EOL;
    }
    else {
        return $enteredS3Token;
    }

    return null;
}


/**
 * @param Dialog $dialogue
 * @return null
 */
function askForS3Secret(Dialog $dialog) {
    echo "Please enter the AWS secret for S3 to be able to upload files.".PHP_EOL;

    while (true) {
        $enteredS3Token = $dialog->ask('Please enter your S3 secret:');
        if (strlen(trim($enteredS3Token)) < 8) {
            echo "That doesn't look like a secret dude.".PHP_EOL;
        }
        else {
            return $enteredS3Token;
        }
    };
}

/**
 * @param Dialog $dialogue
 * @return string
 */
function askForDomainName(Dialog $dialog) {
    $enteredDomain = $dialog->ask('Please enter your domain name:');
    if (strlen(trim($enteredDomain)) > 0) {
        return trim($enteredDomain);
    }
}

/**
 * @param Dialog $dialogue
 * @return string
 */
function askForBucketName(Dialog $dialog) {
    $enteredDomain = $dialog->ask('Please enter the bucket name:');
    if (strlen(trim($enteredDomain)) > 0) {
        return trim($enteredDomain);
    }
}




/**
 * @param Dialog $dialog
 * @return string
 */
function askForConfigDirectory(Dialog $dialog) {
    $configLocationOptions = ['c', 'p'];
    //Config directory - this one or above.
    $directoryOption = $dialog->option(
        'Config file location (c)urrent directory or (p)arent directory?',
        $configLocationOptions,
        'p'
    );

    if ($directoryOption === 'c') {
        $configDirectory = './';
    }
    else {
        $configDirectory = '../';
    }
    
    return $configDirectory;
}


/**
 * @param Dialog $dialog
 * @return null|string
 */
function askForOauthToken(Dialog $dialog) {

    echo "Bastion runs best with an Oauth token. Without one you will be rate limited by Github and be unable to access private repos.".PHP_EOL;

    echo "You can setup an oauth token by going to https://github.com/settings/tokens/new Please enable 'repo' scope to have access to private repos, otherwise select no scopes to just avoid rate-limiting.".PHP_EOL;
//    echo "If you want Bastion to be able to access private repositories, you need to enable the 'repo' scope. If you only have public repos, you can select no scopes to avoid the rate-limiting without giving Bastion any permissions.".PHP_EOL;

    $oauthTokenOptions = ['h', 'n'];

    $option = $dialog->option(
        "Do you (h)ave a token, or want to setup with (n)o token. You can always just enter the token in the config file later.",
        $oauthTokenOptions,
        'h'
    );

    $token = null;

    if ($option === 'h') {
        while ($token == null) {
            $enteredToken = $dialog->ask('Please enter your token:');
            if (strlen(trim($enteredToken)) < 8) {
                echo "That doesn't look like a token dude.";
            }
            else {
                //echo "Checking token ".PHP_EOL;
                //$token = $enteredToken;
                return $enteredToken;
            }
        };
    }
    else {
        echo "Setting up with no token. Downloads will be rate limited and private repos unavailable.".PHP_EOL;
    }

    return null;
}


/**
 * @param Dialogue $dialog
 * @return mixed
 */
function askToSelectUploader(Dialog $dialog) {
    echo "Bastion can upload the artifacts and Satis file to make them available from a server. Please choose an uploader option:".PHP_EOL;

    $uploaderOptions = ['n', 's'];

    $dialogText = 's - Amazon S3'.PHP_EOL;
    $dialogText .= 'n - None, packages will only be available locally.'.PHP_EOL;
    
    $uploaderOptionChosen = $dialog->option(
        $dialogText ,
        $uploaderOptions,
        'X'
    );
    
    $uploaderOptionClasses = [
        'n' => null,
        's' => 'Bastion\S3Sync'
    ];

    if (!array_key_exists('n', $uploaderOptionClasses)) {
        echo "Option chosen `$uploaderOptionChosen` is not listed in uploaderOptionClass";
        exit(0);
    }
    
    return $uploaderOptionClasses[$uploaderOptionChosen];
}

function askToSelectRestriction(Dialog $dialog) {
    echo "Please select an ACL generator. This allows you to tell S3 who can access the files:".PHP_EOL;

    $restrictOptions = ['n', 'i'];

    $dialogText = 'i - Restrict by IP'.PHP_EOL;
    $dialogText = 'n - None, S3 will not apply any ACL check.'.PHP_EOL;

    $restrictOptionChosen = $dialog->option(
        $dialogText ,
        $restrictOptions,
        'X'
    );

    $restrictOptionClass = [
        'n' => '\Bastion\S3ACLNoRestrictionGenerator',
        'i' => '\Bastion\S3ACLRestrictByIPGenerator'
    ];

    if (!array_key_exists($restrictOptionChosen, $restrictOptionClass)) {
        echo "Option chosen `$restrictOptionChosen` is not listed in $restrictOptionClass";
        exit(0);
    }
    
    return $restrictOptionClass[$restrictOptionChosen];
}

/**
 * @param Console $console
 * @return null
 */
function generateConfig(Console $console) {

    $region = null;
    $s3Key = null;
    $s3Secret = null;
    $restrictionClass = 'Bastion\S3ACLRestrictByIPGenerator';

    $dialog = new ConsoleKit\Widgets\Dialog($console);
    $configDirectory = askForConfigDirectory($dialog);
    $token = askForOauthToken($dialog);
    
    $uploaderClass = askToSelectUploader($dialog);

    if ($uploaderClass == 'Bastion\S3Sync') {
        $region = askForRegion($dialog);
        $s3Key = askForS3Key($dialog);
        $s3Secret = askForS3Secret($dialog);
        $restrictionClass = askToSelectRestriction($dialog);

        $domainName = askForBucketName($dialog);
    }

    $domainName = askForDomainName($dialog);
    $zipsDirectory = "./zipsOutput";
    writeConfig($configDirectory, $token, $zipsDirectory, $s3Key, $s3Secret, $region, $domainName, $uploaderClass, $restrictionClass);
    
    return null;
}



/**
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
 * @param $listOfRepositories
 * @param Config $config
 */
//function getArtifacts($ignoreList, $usingList,  Config $config) {

function getArtifacts(ArtifactFetcher $artifactFetcher, Reactor $reactor, $listOfRepositories) {
    $artifactFetcher->downloadZipArtifacts(
        $listOfRepositories
    );
    
    $reactor->run();
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
 * @param \Artax\Request $request
 * @return string
 */
function toCurl(Artax\Request $request) {
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
 * @param Config $config
 * @return \Auryn\Provider
 */
function createInjector(Config $config) {
    $injector = new \Auryn\Provider();
    $injector->alias('Bastion\Config', get_class($config));

    $injector->alias(
        'ArtaxServiceBuilder\ResponseCache',
        'ArtaxServiceBuilder\ResponseCache\FileResponseCache'
    );
    
    $injector->share($config);
    $injector->alias(
        'GithubService\GithubService',
        'GithubService\GithubArtaxService\GithubArtaxService'
    );

    $injector->define('Bastion\S3ACLRestrictByIPGenerator', [':allowedIPAddresses' => []]);
    $injector->alias('Bastion\S3ACLGenerator', $config->getRestrictionClass());

    if ($uploaderClass = $config->getUploaderClass()) {
        $injector->alias('Bastion\Uploader', 'Bastion\S3Sync');
    }

    $injector->define(
        'Bastion\FileStoredRepoInfo',
        [
            ':ignoreListFilename' => realpath(__DIR__)."/../ignoreList.txt",
            ':usingListFilename' => realpath(__DIR__)."/../usingList.txt"
        ]
    );
    $injector->share('Bastion\RepoInfo');
    $injector->alias('Bastion\RepoInfo', 'Bastion\FileStoredRepoInfo');
    
    $injector->share('Alert\Reactor');
    $injector->delegate(
        'Bastion\URLFetcher',
        function (\Artax\Client $client) use ($config) {
            return new \Bastion\URLFetcher($client, $config->getAccessToken());
        }
    );

    $injector->share('Bastion\Progress');
    $injector->delegate('Alert\Reactor', function() {
            return (new ReactorFactory)->select();
    });

    $injector->define(
        'ArtaxServiceBuilder\ResponseCache\FileResponseCache',
        [':cacheDirectory' => __DIR__.'/../cache']
    );
    

//            $asyncClient->setOption('maxconnections', 3);
//            $asyncClient->setOption('connecttimeout', 10);
//            $asyncClient->setOption('transfertimeout', 10);


    $injector->share('Artax\Client');
    $injector->delegate(
        'Artax\Client',
        function (Alert\Reactor $reactor, \Bastion\Progress $progress) {

            //This extends the client, to be able to put a watch on each of the
            //Promises that Artax returns
            $client = new DebugClient($progress, $reactor); 
            $client->setOption(ArtaxClient::OP_MS_KEEP_ALIVE_TIMEOUT, 3);
            $client->setOption(ArtaxClient::OP_HOST_CONNECTION_LIMIT, 4);

            return $client;
        }
    );

    $injector->delegate(
        'GithubService\GithubArtaxService\GithubArtaxService',
        function (\Artax\Client $client, \ArtaxServiceBuilder\ResponseCache $responseCache) use ($config) {
            return new \GithubService\GithubArtaxService\GithubArtaxService($client, $responseCache, "Danack/Bastion");
        }
    );

    $injector->delegate('Aws\S3\S3Client', 'createS3Client');
    $injector->define('Bastion\S3Sync', [':bucket' => $config->getBucketName()]);

    return $injector;
}



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
 * //TODO - shift this to in memory?
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
 * @todo This has a race condition
 * @param bool $dir
 * @param string $prefix
 * @return null|string
 */
function tempdir($directory, $prefix) {
    $path = $directory.'/'.$prefix;
    $path .= '_'.date("Y_m_d_H_i_s").'_'.uniqid();
    if (file_exists($path)) {
        throw new \Bastion\BastionException('Path '.$path.' already exists - that seems highly unlikely.');
    }

    @mkdir($path, 0755, true);
    if (is_dir($path)) {
        return $path;
    }
    return null;
}



function runComposerInstall($directory) {


    //Create the commands
    $input = new ArrayInput([
        'command' => 'install',
        '--no-dev' => true,
        '--working-dir' => $directory
    ]);

    //Create the application and run it with the commands
    $application = new ComposerApplication();
    $application->setAutoExit(false);

    //if (false) {
    $result = $application->run($input);
    //}

    if ($result != 0) {
        echo "Error running composer, see above.";
        exit(0);
    }
    
}

/**
 * @param $sourceDirectory
 * @return RPMProjectConfig
 * @throws BastionException
 */
function readProjectConfig($sourceDirectory) {
    
    $projectConfig = new RPMProjectConfig();

    try {
        $projectConfig->readComposerJsonFile($sourceDirectory.'/composer.json');
    }
    catch (JsonValidationException $jve) {
        echo $jve->getMessage().PHP_EOL;
        foreach($jve->getErrors() as $error) {
            echo $error;
            exit(-1);
        }
    }
    
    return $projectConfig;
}


/**
 * @param $archiveFilename
 * @param $buildDir
 * @throws BastionException
 */
function extractZipAndReturnRootDirectory($archiveFilename, $buildDir) {
    $zip = new ZipArchive;
    $result = $zip->open($archiveFilename);

    if ($result !== true) {
        echo "Failed to open archive ";
        exit(-1);
    }

    $result = $zip->extractTo($buildDir);
    
    if ($result == false) {
        throw new \Bastion\BastionException("Failed to extract archive $archiveFilename.");
    }
    
    $entries = glob($buildDir.'/*', GLOB_ONLYDIR);

    if (count($entries) == 0) {
        throw new \Bastion\BastionException("Archive $archiveFilename did not contain any directories - not a valid archive. ");
    }

    if (count($entries) > 1) {
        throw new \Bastion\BastionException("Archive $archiveFilename contains ".count($entries)." root directories - not a valid archive. ");
    }

    foreach ($entries as $entry) {
        $lastSlashPosition = strrpos($entry, '/');

        if ($lastSlashPosition !== false) {
            //return substr($entry, $lastSlashPosition + 1);
            return $entry;
        }
    }

    throw new \Bastion\BastionException("Failed to find root directory of archive $archiveFilename");
}


/**
 * @param $artifactFilename
 * @param $buildDir
 * @param $repoDirectory
 * @param $version
 * @throws BastionException
 */
function rpmArtifact(
    $artifactFilename,
    $buildDir,
    $repoDirectory,
    $version,
    BuildConfigProvider $buildConfigProvider) {

    if (true) {
        $sourceDirectory = extractZipAndReturnRootDirectory(
            $artifactFilename,
            $buildDir
        );

        runComposerInstall($sourceDirectory);
    }
    else {
        $sourceDirectory = "/documents/projects/github/Bastion/Bastion/temp/BuildRPM_2014_09_07_18_40_36_540ca6a4446f0/intahwebz-master";
    }

    $projectConfig = readProjectConfig($sourceDirectory);
    $buildConfig = $buildConfigProvider->getBuildConfig($sourceDirectory);
    $projectConfig->setVersion($version);

    try {
        $specBuilder = new SpecBuilder($buildConfig, $projectConfig, $buildDir);
    }
    catch (RPMConfigException $ce) {
        echo "Errors detected in config:".PHP_EOL;
        foreach ($ce->getErrors() as $error) {
            echo "   ".$error.PHP_EOL;
        }
        exit(-1);
    }
    
    
    $specBuilder->prepareSetupRPM($sourceDirectory);
    $specBuilder->run();
    $specBuilder->copyPackagesToRepoDir($repoDirectory);
    //copy built files to repo
    //unlink $buildDir
}


$config = getConfig();

if ($config == null) {
    generateConfig($console);
    return;
}

$console = new Console();

$injector = createInjector($config);
