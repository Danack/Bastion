<?php



namespace Bastion;

use Bastion\RPM\BuildConfigProvider;
use Bastion\RPM\RPMConfigException;
use Bastion\RPM\SpecBuilder;
use Bastion\RPM\RPMComposerConfig;
use Composer\Json\JsonValidationException;

use Composer\Console\Application as ComposerApplication;
use Symfony\Component\Console\Input\ArrayInput;


/**
 * @todo This has a race condition
 * @param $directory
 * @param string $prefix
 * @throws BastionException
 * @internal param bool $dir
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


/**
 * Copies the entire contents of a directory recursively to a new
 * directory.
 * @param $sourceDirectory
 * @param $destDirectory
 * @throws BastionException
 */
function copyDirectory($sourceDirectory, $destDirectory) {
    $files = @scandir($sourceDirectory);

    if ($files === false) {
        throw new BastionException(
            "Could not read directory $sourceDirectory to copy files to $destDirectory."
        );
    }
    
    $source = $sourceDirectory."/";
    $destination = $destDirectory."/";

    foreach ($files as $file) {
        if (in_array($file, array(".",".."))) {
            continue;
        }

        $sourceFilename = $source.$file;
        $destFilename = $destination.$file;

        if (is_dir($sourceFilename) == true) {
            @mkdir($destFilename, 0755, true);
            copyDirectory($sourceFilename, $destFilename);
            continue;
        }

        ensureDirectoryExists($destFilename);

        if (!@copy($sourceFilename, $destFilename)) {
            throw new BastionException(
                "Failed to copy file from $sourceFilename to $destFilename"
            );
        }
    }
}


/**
 * @param $directory
 */
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
    $result = $application->run($input);

    if ($result != 0) {
        echo "Error running composer, see above.";
        exit(0);
    }
}



class RPMProcess {

    /** @var Config */
    private $config;

    /**
     * @var BuildConfigProvider
     */
    private $buildConfigProvider;

    /**
     * @param Config $config
     * @param BuildConfigProvider $buildConfigProvider
     */
    function __construct(Config $config, BuildConfigProvider $buildConfigProvider) {
        $this->config = $config;
        $this->buildConfigProvider = $buildConfigProvider;
    }

    /**
     * @param $artifactName
     * @return bool
     */
    function isInIgnoreList($artifactName) {
        if ($artifactName === 'danack_intahwebz_0.2.1.zip') {
            return false;
        }

        return true;
    }
    
    /**
     * 
     */
    function process() {
        $rpmList = $this->config->getRpmList();
        foreach ($rpmList as $rpm) {
            $filePattern = $this->config->getOutputDirectory().'/packages/'.$rpm.'/*.zip';
            $files = glob($filePattern);

            foreach ($files as $file) {
                $artifactName = basename($file);
                if ($this->isInIgnoreList($artifactName) == true) {
                    continue;
                }
                try {
                    $this->processArtifact($file);
                }
                catch (RPMConfigException $ce) {
                    $this->addToIgnoreList($artifactName, $ce);
                }
                catch (\Exception $e) {
                    $this->addToIgnoreList($artifactName, $e);
                }
            }
        }
    }
    
    /**
     * @param $artifactName
     */
    function addToIgnoreList($artifactName) {

    }
    
    
    /**
     * @param $artifactFilename
     * @throws BastionException
     */
    function processArtifact($artifactFilename) {
        echo "check $artifactFilename ".PHP_EOL;
        $tempDir = tempdir($this->config->getTempDirectory(), 'BuildRPM');
        $extractDir = $tempDir.'/intahwebz';
        $buildDir = $tempDir.'/build';

        $version = '0.2.1';

        $sourceDirectory = $this->extractZipAndReturnRootDirectory(
            $artifactFilename,
            $extractDir
        );

        $this->runComposerInstall($sourceDirectory);

        $projectConfig = $this->readProjectConfig($sourceDirectory);
        $buildConfig = $this->buildConfigProvider->getBuildConfig($sourceDirectory);
        $projectConfig->setVersion($version);

        $specBuilder = new SpecBuilder($buildConfig, $projectConfig, $buildDir);
        //@TODO - class envy detected.
        $specBuilder->prepareSetupRPM($sourceDirectory);
        $specBuilder->run();
        $specBuilder->copyPackagesToRepoDir($this->config->getRPMOutputDirectory());
    }


    /**
     * Package a single directory that contains an already extracted project
     * that has both a composer.json and bastion.php file in that directory.
     * @param $directory
     * @throws BastionException
     */
    function packageSingleDirectory($directory) {
        $tempDir = tempdir($this->config->getTempDirectory(), 'BuildRPM');
        $extractDir = $tempDir.'/DanackCodeTest';

        copyDirectory($directory, $extractDir);

        $buildDir = $tempDir.'/build';

        @mkdir($buildDir, 0755, true);
        
        $version = '0.2.1';
        runComposerInstall($extractDir);
        $projectConfig = $this->readProjectConfig($directory);

        $buildConfig = $this->buildConfigProvider->getBuildConfig($directory);
        $projectConfig->setVersion($version);

        $specBuilder = new SpecBuilder($buildConfig, $projectConfig, $buildDir);
        //@TODO - class envy detected.
        $specBuilder->prepareSetupRPM($directory);
        $specBuilder->run();
        $specBuilder->copyPackagesToRepoDir($this->config->getRPMOutputDirectory());
    }

    /**
     * Extract the artifact, and scan to find the 'root' directory. It is assumed that
     * zip/tgz files of source code will have a single root directory containing
     * everything else.
     * @param $archiveFilename
     * @param $extractDirectory
     * @throws BastionException
     * @internal param $buildDir
     */
    function extractZipAndReturnRootDirectory($archiveFilename, $extractDirectory) {
        $zip = new \ZipArchive;
        $result = $zip->open($archiveFilename);
        if ($result !== true) {
            echo "Failed to open archive ";
            exit(-1);
        }

        $result = $zip->extractTo($extractDirectory);
        if ($result == false) {
            throw new \Bastion\BastionException("Failed to extract archive $archiveFilename.");
        }

        $entries = glob($extractDirectory.'/*', GLOB_ONLYDIR);
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
     * @param $sourceDirectory
     * @return RPMComposerConfig
     * @throws BastionException
     */
    function readProjectConfig($sourceDirectory) {
        $projectConfig = new RPMComposerConfig();
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


}

 