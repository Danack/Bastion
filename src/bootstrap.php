<?php

use Bastion\S3Sync;
use Aws\S3\S3Client;
use Bastion\ArtifactFetcher;
use Artax\AsyncClient;
use Alert\ReactorFactory;
use ConsoleKit\Console;
use Bastion\Config;
use Bastion\Uploader;
use ConsoleKit\Widgets\Dialog;

require_once(realpath(__DIR__).'/../vendor/autoload.php');

define('CONFIG_FILE_NAME', 'bastionConfig.php');


/**
 * Class DebugAsyncClient
 */
class DebugAsyncClient extends AsyncClient {

    function request($uriOrRequest, callable $onResponse, callable $onError) {
        echo "Making request: ";
        if (is_string($uriOrRequest)) {
            echo "string: ". $uriOrRequest;
        }
        else if ($uriOrRequest instanceof \Artax\Request) {
            echo "Request instance: ".$uriOrRequest->getUri();
            echo "\n".toCurl($uriOrRequest);
        }
        else {
            echo "class ".get_class($uriOrRequest);
        }
        echo "\n";

        return parent::request($uriOrRequest, $onResponse, $onError);
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
    $region,
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

    \$zipsDirectory = '$zipsDirectory';
    \$accessToken = $tokenString;
    \$s3Key = '$s3Key';
    \$s3Secret = '$s3Secret';
    \$region = '$region';
    \$domainName = '$domainName';
    
    // Uploader class should be one of 
    // null - skip uploading
    // 'Bastion\S3Sync' - the included S3 uploader.
    //
    // Or any class that implements the
    
    \$uploaderClass = '$uploaderClass';
    
    //If S3 is chosen as the uploader, the restriction class will be
    //applied to restrict who can access the files
    \$restrictionClass = '$restrictionClass';

    \$repoList = [
        //Put the list of github repos that you want to have available
        //in here as "owner/repo" e.g.
        //
        //"Behat/Mink",
    ];

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

    while ($token == null) {
        $enteredS3Token = $dialog->ask('Please enter your S3 token:');
        if (strlen(trim($enteredToken)) < 8) {
            echo "That doesn't look like a token dude.".PHP_EOL;
        }
        else {
            return $enteredS3Token;
        }
    };

    return null;
}


/**
 * @param Dialog $dialogue
 * @return null
 */
function askForS3Secret(Dialog $dialog) {
    echo "Please enter the AWS secret for S3 to be able to upload files.".PHP_EOL;

    while ($token == null) {
        $enteredS3Token = $dialog->ask('Please enter your S3 secret:');
        if (strlen(trim($enteredToken)) < 8) {
            echo "That doesn't look like a secret dude.".PHP_EOL;
        }
        else {
            return $enteredS3Token;
        }
    };

    return null;
}

/**
 * @param Dialog $dialogue
 * @return string
 */
function askForDomainName(Dialog $dialog) {

//    $enteredDomain = null;
//    
//    while ($enteredDomain == null) {
        $enteredDomain = $dialog->ask('Please enter your domain name:');
        if (strlen(trim($enteredDomain)) > 0) {
            return trim($enteredDomain);
        }
    //};
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
 * @return \Bastion\Config|null
 */
function getConfig() {

    $configLocations = [
        realpath(__DIR__)."/../../".CONFIG_FILE_NAME, //outside of project
        realpath(__DIR__)."/../".CONFIG_FILE_NAME, //project root
    ];
    

    foreach ($configLocations as $configLocation) {
        if (file_exists($configLocation) == true) {
            $zipsDirectory = null;
            $accessToken = null;
            $repoList = null;

            include_once $configLocation;

            if (isset($zipsDirectory) == false) {
                echo "zipsDirectory is not set, cannot proceed.";
                exit(0);
            }
//            if (isset($accessToken) == false) {
//                echo "accessToken is not set, cannot proceed.";
//                exit(0);
//            }

            if (isset($repoList) == false) {
                echo "repoList is not set, cannot proceed.";
                exit(0);
            }
            
            //TODO - check all values, not just a couple.

            $isDryRun = false;
            $config = new \Bastion\Config($zipsDirectory, $isDryRun, $accessToken, $repoList);

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

    //$reactor = (new ReactorFactory)->select();
    //$asyncClient = new DebugAsyncClient($reactor);
//    $asyncClient->setOption('maxconnections', 3);
//    $asyncClient->setOption('connecttimeout', 10);
//    $asyncClient->setOption('transfertimeout', 10);

    //$repoInfo = new \Bastion\FileStoredRepoInfo($ignoreList, $usingList);
    //$githubAPI = new \GithubService\GithubArtaxService\GithubArtaxService($asyncClient, "Danack/Bastion");

    //$downloader = new \Bastion\URLFetcher($asyncClient, $config->getAccessToken());

//    $artifactFetcher = new ArtifactFetcher(
//        $githubAPI,
//        $downloader,
//        $repoInfo,
//        $config
//    );

    $artifactFetcher->downloadZipArtifacts(
        $listOfRepositories
    );
    
    $reactor->run();
}

/**
 * @param $outputDirectory
 * @param $siteURL
 */
function fixPaths($outputDirectory, $siteURL) {
    $outputDirectory = $outputDirectory."/packages";
    $absolutePath = dirname(realpath($outputDirectory));

    $src = $outputDirectory."./packages.json";
    $text = file_get_contents($src);
    $text = str_replace($absolutePath, $siteURL, $text);
    file_put_contents($src, $text);

    $src = $outputDirectory."/index.html";
    $text = file_get_contents($src);
    $text = str_replace($absolutePath, "", $text);
    file_put_contents($src, $text);
}

/**
 * @param Uploader $uploader
 */
function syncArtifactBuild(Uploader $uploader) {
    $uploader->putFile("./zipsOutput/index.html", 'index.html');
    $uploader->putFile("./zipsOutput/packages.json", 'packages.json');
    $uploader->syncDirectory("./zipsOutput/packages/", "packages");
    $uploader->finishProcessing();
}

/**
 * @param $awsServicesKey
 * @param $awsServicesSecret
 * @param $allowedIPAddresses
 */
function syncSatisBuild($awsServicesKey, $awsServicesSecret, $allowedIPAddresses) {
    $sync = new S3Sync(
        $awsServicesKey,
        $awsServicesSecret,
        "satis.basereality.com",
        $allowedIPAddresses
    );

    $sync->putFile("./satisOutput/index.html", 'satis-public/index.html');
    $sync->putFile("./satisOutput/packages.json", 'satis-public/packages.json');
    $sync->syncDirectory("./satisOutput/packages/", "satis-public/packages");
    $sync->updateACL(false);
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


/**
 * @param Config $config
 */
function createInjector(Config $config) {

    $injector = new \Auryn\Provider();

    $injector->share('Alert\Reactor');
    $injector->delegate(
        'Bastion\URLFetcher',
        function (\Artax\AsyncClient $asyncClient) use ($config) {
            new \Bastion\URLFetcher($asyncClient, $config->getAccessToken());
        }
    );

    $injector->delegate(
        'Artax\AsyncClient',
        function (Alert\Reactor $reactor) {
            $asyncClient = new DebugAsyncClient($reactor);
            $asyncClient->setOption('maxconnections', 3);
            $asyncClient->setOption('connecttimeout', 10);
            $asyncClient->setOption('transfertimeout', 10);

            return $asyncClient;
        }
    );

    $injector->delegate(
        'GithubService\GithubArtaxService\GithubArtaxService',
        function (\Artax\AsyncClient $asyncClient) use ($config) {
            new \GithubService\GithubArtaxService\GithubArtaxService($asyncClient, "Danack/Bastion");
        }
    );

    $injector->delegate(
        'Aws\S3\S3Client',
        function () use ($config) {
            $s3Client = S3Client::factory(
                [
                    'key' => $config->getS3Key(),
                    'secret' => $config->getS3Secret(),
                    'region' => $config->getRegion()
                ]
            );
        }
    );

    $injector->alias('Bastion\S3ACLGenerator', $config->getRestrictionClass());

    $injector->define(
        'Bastion\FileStoredRepoInfo',
        [':ignoreList' => realpath(__DIR__)."/../ignoreList.txt",
         ':usingList' => realpath(__DIR__)."/../usingList.txt"
        ]
    );

    $injector->define('Bastion\S3Sync', [':bucket' => $config->getBucketName()]);

}