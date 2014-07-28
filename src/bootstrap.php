<?php

use Bastion\S3Sync;
use Aws\S3\S3Client;
use Bastion\ArtifactFetcher;
use Artax\AsyncClient;
use Alert\ReactorFactory;
use ConsoleKit\Console;
use Bastion\Config;

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
function writeConfig($configDirectory, $token, $zipsDirectory) {
    $filename = realpath(__DIR__).'/../'.$configDirectory.'/'.CONFIG_FILE_NAME;

    $tokenString = 'null';
    if ($token) {
        $tokenString = "'$token'";
    }
    
    $configBody = <<< END
<?php

    \$zipsDirectory = '$zipsDirectory';

    \$accessToken = $tokenString;

    \$repoList = [
        //Put the list of github repos that you want to have available
        //in here as "owner/repo" e.g.
        //
        //"Behat/Mink",
    ];

    //define('AWS_SERVICES_KEY
    //define('AWS_SERVICES_SECRET
    
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
 * @param Console $console
 * @return null
 */
function generateConfig(Console $console) {

    $dialog = new ConsoleKit\Widgets\Dialog($console);
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
                $token = $enteredToken;
            }
        };
    }
    else {
        echo "Setting up with no token. Downloads will be rate limited and private repos unavailable.".PHP_EOL;
    }

    $zipsDirectory = "./zipsOutput";
    writeConfig($configDirectory, $token, $zipsDirectory);
    
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
function getArtifacts($ignoreList, $usingList,  Config $config) {
    $listOfRepositories = $config->getRepoList();
    $reactor = (new ReactorFactory)->select();
    $asyncClient = new DebugAsyncClient($reactor);
    $asyncClient->setOption('maxconnections', 3);
    $asyncClient->setOption('connecttimeout', 10);
    $asyncClient->setOption('transfertimeout', 10);
    
    $repoInfo = new \Bastion\FileStoredRepoInfo($ignoreList, $usingList);
    $githubAPI = new \GithubService\GithubArtaxService\GithubArtaxService($asyncClient, "Danack/Bastion");

    $downloader = new \Bastion\URLFetcher($asyncClient, $config->getAccessToken());

    $artifactFetcher = new ArtifactFetcher(
        $githubAPI,
        $downloader,
        $repoInfo,
        $config
    );

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

function syncArtifactBuild($awsServicesKey, $awsServicesSecret, $allowedIPAddresses) {

    $s3Client = S3Client::factory(
        [
            'key' => AWS_SERVICES_KEY,
            'secret' => AWS_SERVICES_SECRET,
            'region' => 'eu-west-1'
        ]
    );

    $sync = new S3Sync(
        "satis.basereality.com",
        $allowedIPAddresses,
        $s3Client
    );

    $sync->putFile("./zipsOutput/index.html", 'index.html');
    $sync->putFile("./zipsOutput/packages.json", 'packages.json');
    $sync->syncDirectory("./zipsOutput/packages/", "packages");
    $sync->updateACL(false);
}


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


