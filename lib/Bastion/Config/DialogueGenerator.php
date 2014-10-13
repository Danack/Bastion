<?php


namespace Bastion\Config;

use Danack\Console\Question\OptionQuestion;
use Danack\Console\Question\Question;
use Danack\Console\Helper\QuestionHelper;
use Danack\Console\Input\InputInterface;
use Danack\Console\Output\OutputInterface;


class DialogueGenerator {

    private $questionHelper;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;
    
    function __construct(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper
    ) {
        $this->questionHelper = $questionHelper;
        $this->input = $input;
        $this->output = $output;
    }

    private function ask(Question $question) {
        return $this->questionHelper->ask(
            $this->input,
            $this->output,
            $question
        );
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
        $filename = realpath(__DIR__).'/../../../'.$configDirectory.'/'.CONFIG_FILE_NAME;

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
     * @return string
     */
    function askForRegion() {

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

        $question = new OptionQuestion(
            'Please enter a digit',
            $regionOptions,
            'p'
        );
        $question->setErrorMessage(
            "Please enter a digit that corresponds to one of the regions listed."
        );
        $regionOption = $this->ask($question);

        return $regionOption;
    }

    
    function askForS3Key() {
        $this->output->writeln("Please enter the AWS key for S3 to be able to upload files.");

        $validatorCallable = function ($answer) {
            $enteredS3Token = trim($answer);
            if (strlen(trim($enteredS3Token)) < 8) {
                throw new \RuntimeException(
                    "That doesn't look like a token dude. Should be at least 8 chars."
                );
            }
            
            return $enteredS3Token;
        };
        
        $question = new Question('Please enter your S3 token:');
        $question->setValidator($validatorCallable);
        $enteredS3Token = $this->ask($question);

        return $enteredS3Token;
    }


    /**
     * @return string
     */
    function askForS3Secret() {
        $this->output->writeln("Please enter the AWS secret for S3 to be able to upload files.");

        $validatorCallable = function ($answer) {
            $enteredS3Token = trim($answer);
            if (strlen(trim($enteredS3Token)) < 8) {
                throw new \RuntimeException(
                    "That doesn't look like a token dude. Should be at least 8 chars."
                );
            }

            return $enteredS3Token;
        };

        $question = new Question('Please enter your S3 secret:');
        $question->setValidator($validatorCallable);
        
        return $this->ask($question);
    }

    /**
     * @return string
     */
    function askForDomainName() {

        $question = new Question('<question>Please enter your domain name:</question>');
        //$question->setValidator();
        
        $enteredDomain = $this->ask($question);
        if (strlen(trim($enteredDomain)) > 0) {
            return trim($enteredDomain);
        }

        return '';
    }

    /**
     * @return string
     */
    function askForBucketName() {
        $question = new Question('Please enter the bucket name:');
        $enteredDomain = $this->ask($question);
        if (strlen(trim($enteredDomain)) > 0) {
            return trim($enteredDomain);
        }
        return '';
    }

    /**
     * @return string
     */
    function askForConfigDirectory() {
        $configLocationOptions = [
            'c' => 'Current directory', 
            'p' => 'Parent directory'];
        //Config directory - this one or above.

        $question = new OptionQuestion(
            '<question>Config file location (c)urrent directory or (p)arent directory?</question>',
            $configLocationOptions,
            'p'
        );

        $directoryOption = $this->ask($question);

        if ($directoryOption === 'c') {
            $configDirectory = './';
        }
        else {
            $configDirectory = '../';
        }

        return $configDirectory;
    }


    /**
     * @return null|string
     */
    function askForOauthSetup() {

        echo "Bastion runs best with an Oauth token. Without one you will be rate limited by Github and be unable to access private repos.".PHP_EOL;

        echo "You can setup an oauth token by going to https://github.com/settings/tokens/new Please enable 'repo' scope to have access to private repos, otherwise select no scopes to just avoid rate-limiting.".PHP_EOL;
//    echo "If you want Bastion to be able to access private repositories, you need to enable the 'repo' scope. If you only have public repos, you can select no scopes to avoid the rate-limiting without giving Bastion any permissions.".PHP_EOL;

        $oauthTokenOptions = [
            'h' => 'Have a token and want to enter it',
            'n' => 'Setup with no Oauth token'
        ];

        $question = new OptionQuestion(
            '<question>Do you (h)ave a token, or want to setup with (n)o token. You can always just enter the token in the config file later.</question>',
            $oauthTokenOptions,
            'h'
        );

//        $question->setErrorMessage(
//            "Please enter a digit that corresponds to one of the regions listed."
//        );
        $option = $this->ask($question);

        
        if ($option === 'h') {
            return $this->askForOauthToken();
        }
        else {
            echo "Setting up with no token. Downloads will be rate limited and private repos unavailable.".PHP_EOL;
        }

        return null;
    }

    /**
     * @return string
     */
    function askForOauthToken() {
        $validator = function ($enteredToken) {
            if (strlen(trim($enteredToken)) < 8) {
                //echo "That looks a bit short to be a token dude.";
                throw new \Exception("That looks a bit short to be a token dude.");
            }
             
            return $enteredToken;
        };
         
        $question = new Question('Please enter your Oauth token:');
        $question->setValidator($validator);
        $token = $this->ask($question);
        
        return $token;
    }
    
    
    /**
     * @return mixed
     */
    function askToSelectUploader() {
        echo "Bastion can upload the artifacts and Satis file to make them available from a server. Please choose an uploader option:".PHP_EOL;

        $uploaderOptions = [
            's' => 'Amazon S3',
            'n' => 'None, packages will only be available locally.'
        ];

        $question = new OptionQuestion(
            '"Please select an ACL generator. This allows you to tell S3 who can access the files:',
            $uploaderOptions,
            'n'
        );

        $uploaderOptionChosen = $this->ask($question);

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

    /**
     * @return mixed
     */
    function askToSelectRestriction() {
        $restrictOptions = [
            'n' => 'None, S3 will not apply any ACL check.',
            'i' => 'Restrict by IP'
        ];
        
        $question = new OptionQuestion(
            '"Please select an ACL generator. This allows you to tell S3 who can access the files:',
            $restrictOptions,
            'n'
        );

        $restrictOptionChosen = $$this->ask($question);

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
     * @return null
     */
    function generateConfig() {

        $region = null;
        $s3Key = null;
        $s3Secret = null;
        $restrictionClass = 'Bastion\S3ACLRestrictByIPGenerator';
        $configDirectory = $this->askForConfigDirectory();
        $token = $this->askForOauthSetup();
        $uploaderClass = $this->askToSelectUploader();
        $domainName = 'null';

        if ($uploaderClass == 'Bastion\S3Sync') {
            $region = $this->askForRegion();
            $s3Key = $this->askForS3Key();
            $s3Secret = $this->askForS3Secret();
            $restrictionClass = $this->askToSelectRestriction();
            $domainName = $this->askForBucketName();
        }
        
        $zipsDirectory = "./zipsOutput";
        $this->writeConfig($configDirectory, $token, $zipsDirectory, $s3Key, $s3Secret, $region, $domainName, $uploaderClass, $restrictionClass);

        return null;
    }
}

 