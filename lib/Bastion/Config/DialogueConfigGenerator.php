<?php


namespace Bastion\Config;

use Danack\Console\Question\OptionQuestion;
use Danack\Console\Question\Question;
use Danack\Console\Helper\QuestionHelper;
use Danack\Console\Input\InputInterface;
use Danack\Console\Output\OutputInterface;
use GithubService\GithubArtaxService\GithubArtaxService;

use Composer\IO\ConsoleIO;
use Composer\Util\ProcessExecutor;
use GithubService\OneTimePasswordSMSException;
use GithubService\OneTimePasswordAppException;



class DialogueConfigGenerator {

    private $questionHelper;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    private $githubArtaxService;
    
    private $io;
    
    private $process;
    
    function __construct(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper,
        GithubArtaxService $githubArtaxService
    ) {
        $this->questionHelper = $questionHelper;
        $this->input = $input;
        $this->output = $output;
        $this->githubArtaxService = $githubArtaxService;
        //$this->io = new ConsoleIO($input, $output);
        //$this->process = new ProcessExecutor($this->io);
    }

    /**
     * @param Question $question
     * @return string
     */
    private function askQuestion(Question $question) {
        return $this->questionHelper->ask(
            $this->input,
            $this->output,
            $question
        );
    }
    
    private function ask($questionString) {
        $question = new Question($questionString);

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

\$rpmList = [
];

\$tempDirectory = './temp';
\$rpmDirectory  = './rpmRepo';
\$bucketName = null;


//The config file must return a config object.
return new \Bastion\Config\Config(
    \$zipsDirectory,        //outputDirectory,
    \$tempDirectory,        //tempDirectory,
    \$rpmDirectory,         //rpmDirectory
    \$githubAccessToken,    //accessToken
    \$repoList,             //repoList
    \$rpmList,              //rpmList
    \$restrictionClass,     //restrictionClass 
    \$bucketName,           //bucketName
    \$s3Key,                //s3Key
    \$s3Secret,             //s3Secret
    \$s3Region,             //s3Region
    \$uploaderClass         //uploaderClass
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

        $this->output->writeln("Please choose a region for the S3 bucket to be create in. This only has any effect if the bucket doesn't already exist.");

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
        $regionOption = $this->askQuestion($question);

        return $regionOption;
    }

    /**
     * @return string
     */
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
        $enteredS3Token = $this->askQuestion($question);

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
        
        return $this->askQuestion($question);
    }

    /**
     * @return string
     */
    function askForDomainName() {

        $question = new Question('<question>Please enter your domain name:</question>');
        //$question->setValidator();
        
        $enteredDomain = $this->askQuestion($question);
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
        $enteredDomain = $this->askQuestion($question);
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
            'r' => 'Root directory of Bastion', 
            'p' => 'Parent directory of Bastion'];
        //Config directory - this one or above.

        $question = new OptionQuestion(
            '<question>Where should Bastion write the generated config file?</question>',
            $configLocationOptions,
            'p'
        );

        $directoryOption = $this->askQuestion($question);

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

        $this->output->writeln('');
        $this->output->writeln("Bastion runs best with an Oauth token. Without one you will be rate limited by Github and be unable to access private repos.");

        $this->output->writeln("Bastion can create an Oauth token for you. This requires entering your Github username and password. The username and password are not stored - only the token is.");
        
        $this->output->writeln("You can also setup an oauth token by going to https://github.com/settings/tokens/new Please enable 'repo' scope to have access to private repos, otherwise select no scopes to just avoid rate-limiting.");
//    echo "If you want Bastion to be able to access private repositories, you need to enable the 'repo' scope. If you only have public repos, you can select no scopes to avoid the rate-limiting without giving Bastion any permissions.".PHP_EOL;

        $oauthTokenOptions = [
            'h' => 'I have a token and want to enter it.',
            'p' => 'Create a token to access my private repos.',
            'a' => 'Create a token without access to my private repos.',
            'n' => 'Setup with no Oauth token.',
        ];

        $question = new OptionQuestion(
            '<question>How do you want to setup the Oauth token?</question> You can always just enter the token in the config file later.',
            $oauthTokenOptions,
            'h'
        );

        $option = $this->askQuestion($question);

        if ($option === 'h') {
            return $this->askForOauthToken();
        }
        else if ($option === 'p') {
            return $this->createGithubOauthToken(true);
        }
        else if ($option === 'a') {
            return $this->createGithubOauthToken(false);
        }
        else {
            $this->output->writeln("Setting up with no token. Downloads will be rate limited and private repos unavailable.");
        }

        return null;
    }

    /**
     * @param $accessPrivateRepo
     */
    function createGithubOauthToken($accessPrivateRepo) {

        $scopes = [];

        if ($accessPrivateRepo) {
            $scopes[] = 'repo';
        }
        
        $username = $this->ask('Username: ');
        $password = $this->askAndHideAnswer('Password: ');
        
        
 
        $otp = false;

        for ($x=0 ; $x<5 ; $x++) {
            $usernamePassword = $username.':'.$password;

            $usernamePassword = '***REMOVED***:***REMOVED***';

            $applicationName = 'Bastion';
            //    if (0 === $this->process->execute('hostname', $output)) {
            //        $appName .= ' on ' . trim($output);
            //    }
            //    

            $currentAuthCommand = $this->githubArtaxService->basicListAuthorizations($usernamePassword);

            $currentAuths = $currentAuthCommand->execute();
            $currentAuth = $currentAuths->findNoteAuthorization($applicationName);

            if ($currentAuth) {
                //echo "Already have an auth:";
                return $currentAuth->token;
            }

            $permissionCommand = $this->githubArtaxService->basicAuthToOauth(
                $usernamePassword,
                $scopes,
                $applicationName,
                'http://www.bastionrpm.com'
            );

            try {
                $result = $permissionCommand->execute();
            }
            catch (OneTimePasswordSMSException $otpse) {
                echo "SMS";
                var_dump($otpse);

            }
            catch (OneTimePasswordAppException $otpae) {
                echo "app";
                var_dump($otpae);
            }

            catch (\ArtaxServiceBuilder\BadResponseException $bre) {
                echo "Exception is: ".$bre->getMessage();
                var_dump($bre->getResponse()->getAllHeaders());
                var_dump($bre->getResponse()->getOriginalRequest());
            }
        }
    }
    

    /**
     * @return string
     */
    function askForOauthToken() {
        $validator = function ($enteredToken) {
            if (strlen(trim($enteredToken)) < 8) {
                throw new \Exception("That looks too short to be a token. Please enter again.");
            }
             
            return $enteredToken;
        };
         
        $question = new Question('Please enter your Oauth token:');
        $question->setValidator($validator);
        $token = $this->askQuestion($question);
        
        return $token;
    }
    
    
    /**
     * @return mixed
     */
    function askToSelectUploader() {
        $this->output->writeln("Bastion can upload the artifacts and Satis file to make them available from a server. Please choose an uploader option:");

        $uploaderOptions = [
            's' => 'Amazon S3',
            'n' => 'None, packages will only be available locally.'
        ];

        $question = new OptionQuestion(
            '"Please select an ACL generator. This allows you to tell S3 who can access the files:',
            $uploaderOptions,
            'n'
        );

        $uploaderOptionChosen = $this->askQuestion($question);

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
            $this->output->writeln("Option chosen `$restrictOptionChosen` is not listed in $restrictOptionClass");
            exit(-1);
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



    public function askAndHideAnswer($question)
    {
        // handle windows
        /* if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $finder = new ExecutableFinder();

            // use bash if it's present
            if ($finder->find('bash') && $finder->find('stty')) {
                $this->write($question, false);
                $value = rtrim(shell_exec('bash -c "stty -echo; read -n0 discard; read -r mypassword; stty echo; echo $mypassword"'));
                $this->write('');

                return $value;
            }

            // fallback to hiddeninput executable
            $exe = __DIR__.'\\hiddeninput.exe';

            // handle code running from a phar
            if ('phar:' === substr(__FILE__, 0, 5)) {
                $tmpExe = sys_get_temp_dir().'/hiddeninput.exe';

                // use stream_copy_to_stream instead of copy
                // to work around https://bugs.php.net/bug.php?id=64634
                $source = fopen(__DIR__.'\\hiddeninput.exe', 'r');
                $target = fopen($tmpExe, 'w+');
                stream_copy_to_stream($source, $target);
                fclose($source);
                fclose($target);
                unset($source, $target);

                $exe = $tmpExe;
            }

            $this->write($question, false);
            $value = rtrim(shell_exec($exe));
            $this->write('');

            // clean up
            if (isset($tmpExe)) {
                unlink($tmpExe);
            }

            return $value;
        } */

        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
            foreach (array('bash', 'zsh', 'ksh', 'csh') as $sh) {
                if ('OK' === rtrim(shell_exec(sprintf($test, $sh)))) {
                    $shell = $sh;
                    break;
                }
            }
            if (isset($shell)) {
                $this->output->write($question, false);
                $readCmd = ($shell === 'csh') ? 'set mypassword = $<' : 'read -r mypassword';
                $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
                $value = rtrim(shell_exec($command));
                $this->output->write('');

                return $value;
            }
        }


        // not able to hide the answer, proceed with normal question handling
        return $this->ask($question);
    }
    
    
}

 