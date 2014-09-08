<?php

namespace Bastion\RPM;

use Bastion\BastionException;


class SpecBuilder {

    /** @var RPMBuildConfig  */
    private $buildConfig;
    /** @var RPMProjectConfig  */
    private $projectConfig;

    private $filesMacro = '';
    private $buildScript = '';
    private $installDir;
    
    private $buildDir;
    
    private $specFilename = 'intahwebz';
    

    function __construct(RPMBuildConfig $buildConfig, RPMProjectConfig $projectConfig, $buildDir) {
        $buildConfig->checkData();
        $projectConfig->checkData();
        $this->buildDir = realpath($buildDir);
        
        if ($this->buildDir == null) {
            throw new RPMConfigException("Temp directory for building RPM, `$buildDir` is not readable.", []);
        }
        if (strlen($this->buildDir) < 8) {
            throw new RPMConfigException("Temp directory `$buildDir` does not include BuildRPM; maybe symlink has gone weird?", []);
        }

        $this->buildDir .= '/';
        
        //cloning prevents mutability
        $this->buildConfig = clone $buildConfig;
        $this->projectConfig = clone $projectConfig;
        $this->installDir = $projectConfig->getInstallDir();

        $this->filesMacro = sprintf(
            "%%files
%%defattr(555,%s,%s)\n",
            $buildConfig->getUnixUser(),
            $buildConfig->getUnixGroup()
        );
    }

    private function addInstallFiles() {
        foreach ($this->buildConfig->getRPMInstallFiles() as $file) {
            $src = $file->getSourceFilename();
            $dest = $file->getDestFilename();
            $dirname = dirname($dest);
            $this->buildScript .= "mkdir -p \$RPM_BUILD_ROOT/$dirname\n";
            $this->buildScript .= "cp \$RPM_BUILD_DIR/$src \$RPM_BUILD_ROOT/$dest \n";
            //todo - adjust attr
            $this->filesMacro .= "$dest\n";
        }
    }


    private function addPostBuildScripts() {
        foreach ($this->buildConfig->getScripts() as $script) {
            $this->buildScript .=  $script."\n";
        }
    }


    /**
     * 
     */
    private function processCrontab() {
        $crontabFiles = $this->buildConfig->getCrontabFiles();
        if (count($crontabFiles)) {
            $this->buildScript .= "mkdir -p \$RPM_BUILD_ROOT/etc/crond.d\n";
        }

        foreach ($crontabFiles as $crontabEntry) {
            $this->buildScript .= "cp \$RPM_BUILD_DIR/$crontabEntry \$RPM_BUILD_ROOT/etc/crond.d\n";
            $filepart = basename($crontabEntry);
            $this->filesMacro .= "/etc/crond.d/$filepart\n";
        }
    }

    /**
     * 
     */
    function addDataDirectories() {
        foreach ($this->buildConfig->getRPMDataDirectories() as $rpmDataDirectory) {
            $modeString = "-";
            $mode = $rpmDataDirectory->getMode();
            if ($mode !== false) {
                $modeString = sprintf("0%o", $mode);
            }

            $this->buildScript .= sprintf(
                "mkdir -p \$RPM_BUILD_ROOT/%s/%s \n",
                $this->projectConfig->getInstallDir(),
                $rpmDataDirectory->getDirectory()
            );

            $this->filesMacro .= sprintf(
                "%%attr(%s, %s, %s) %%dir %s/%s\n",
                $modeString,
                $rpmDataDirectory->getUser(),
                $rpmDataDirectory->getGroup(),
                $this->projectConfig->getInstallDir(),
                $rpmDataDirectory->getDirectory()
            );
        }
    }

    /**
     * 
     */
    function addSourceDirectories() {
        foreach ($this->buildConfig->getSourceDirectories() as $srcDirectory) {
            $this->filesMacro .= sprintf(
                "%s/%s/*\n",
                $this->projectConfig->getInstallDir(),
                $srcDirectory
            );

            $this->buildScript .= sprintf(
                "mkdir -p \$RPM_BUILD_ROOT/%s/%s \n",
                $this->projectConfig->getInstallDir(),
                $srcDirectory
            );

            $this->buildScript .= sprintf(
                "cp -R \$RPM_BUILD_DIR/%s/* \$RPM_BUILD_ROOT/%s/%s/ \n",
                $srcDirectory,
                $this->projectConfig->getInstallDir(),
                $srcDirectory
            );
        }
    }

    /**
     * 
     */
    function addSourceFiles() {
        foreach ($this->buildConfig->getSourceFiles() as $srcFile) {
            $this->filesMacro .= sprintf(
                "%s/%s\n",
                $this->projectConfig->getInstallDir(),
                $srcFile
            );
            
            $this->buildScript .= sprintf(
                "cp \$RPM_BUILD_DIR/%s \$RPM_BUILD_ROOT/%s/%s \n",
                $srcFile,
                $this->projectConfig->getInstallDir(),
                $srcFile
            );
        }
    }

    /**
     * @param $sourceDirectory
     * @throws BastionException
     * @internal param $destDirectory
     */
    function copyPackageToBuild($sourceDirectory) {
        $destDirectory = $this->buildDir.'BUILD';
        $this->copyDirectory($sourceDirectory, $destDirectory);
    }
     
    function copyDirectory($sourceDirectory, $destDirectory) {
        $files = scandir($sourceDirectory);
        $source = $sourceDirectory."/";
        $destination = $destDirectory."/";

        foreach ($files as $file) {
            if (in_array($file, array(".",".."))) {
                continue;
            }

            $sourceFilename = $source.$file;
            $destFilename = $destination.$file;
            
            if (is_dir($sourceFilename) == true) {
//                echo $file.PHP_EOL;
                @mkdir($destFilename, 0755, true);
                $this->copyDirectory($sourceFilename, $destFilename);
                continue;
            }

            if (!@copy($sourceFilename, $destFilename)) {
                throw new BastionException(
                    "Failed to copy file from $sourceFilename to $destFilename"
                );
            }
        }
    }


    /**
     * Generates the spec file and prepares the required directories.
     * @param $sourceDirectory
     * @throws BastionException
     */
    function prepareSetupRPM($sourceDirectory) {
        //Clean out any old directory
        @unlink($this->buildDir.'BUILD');
        if (is_dir($this->buildDir.'BUILD') == true) {
            throw new BastionException(
                "BUILD directory already exists in directory ".$this->buildDir." which means the attempt to delete it failed."
            );
        }

        //Create the RPM directories
        @mkdir($this->buildDir.'BUILD', 0755, true);
        @mkdir($this->buildDir.'BUILDROOT', 0755, true);
        @mkdir($this->buildDir.'RPMS', 0755, true);
        @mkdir($this->buildDir.'SOURCES', 075, true);
        @mkdir($this->buildDir.'SPECS', 0755, true);
        @mkdir($this->buildDir.'SRPMS', 0755, true);
        $this->copyPackageToBuild($sourceDirectory);
        $specFile = $this->generateSpec();
        $fullSpecFilename = $this->buildDir.'SPECS/'.$this->specFilename.'.spec';
        file_put_contents($fullSpecFilename, $specFile);
    }
    
    /**
     * Generates the RPM SPEC file
     * @return string
     */
    function generateSpec() {
        $projectName = $this->projectConfig->getName();
        $version = $this->projectConfig->getVersion();
        $unmangledVersion = $this->projectConfig->getUnmangledVersion();
        $release = $this->projectConfig->getRelease();
        $summary = $this->projectConfig->getSummary();

        $prepScript = sprintf(
            "%%{__mkdir} -p \$RPM_BUILD_ROOT%s",
            $this->projectConfig->getInstallDir()
        );

        $installScript = "";
    
        $this->processCrontab();
        $this->addInstallFiles();
        $this->addDataDirectories();
        $this->addSourceDirectories();
        $this->addSourceFiles();

        $license = null;
        $licenseString = "License: None";
    
        if ($license = $this->projectConfig->getLicense()) {
            //@TODO - get from project config
            $licenseString = "License: $license)";
            
        }

        $fullDescription = $this->projectConfig->getFullDescription();
        $arch = $this->projectConfig->getArch();
        $group = $this->projectConfig->getRPMGroup();

        $cleanScript = "rm -rf \$RPM_BUILD_ROOT";

        //This should be the last addition to the build script
        $this->addPostBuildScripts();

        $buildScript = $this->buildScript;
        $filesMacro = $this->filesMacro;

        $createUserString = <<< END
/usr/bin/getent group %s || groupadd -r %s
/usr/bin/getent passwd %s || useradd --key UMASK=0022 -m -g %s %s    
END;
        
        $preScript = sprintf(
            $createUserString,
            $this->buildConfig->getUnixGroup(),
            $this->buildConfig->getUnixGroup(),
            $this->buildConfig->getUnixUser(),
            $this->buildConfig->getUnixGroup(),
            $this->buildConfig->getUnixUser()
       );

        $specContents = <<< END
%define name $projectName
%define version $version
%define unmangled_version $unmangledVersion
%define release $release

Summary: $summary
Name: %{name}
Version: %{version}
Release: %{release}
#Source0: %{name}-%{unmangled_version}.tar.gz
$licenseString
Group: $group
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-buildroot
Prefix: %{_prefix}
BuildArch: $arch
AutoReqProv: no
Requires(pre): /usr/sbin/useradd, /usr/bin/getent

%pre
$preScript
        
%description
$fullDescription

%prep
$prepScript

%build
$buildScript

%install
$installScript

%clean
$cleanScript

$filesMacro

END;

        return $specContents;
    }

    /**
     * @throws BastionException
     */
    function run() {
        $returnValue = 0;
        $startDirectory = getcwd();
        $changedCorrectly = chdir($this->buildDir);

        if ($changedCorrectly == false) {
            throw new BastionException("Failed to enter directory ".$this->buildDir." to build RPM SPEC.");
        }

        echo "Building RPM spec in directory ".getcwd().PHP_EOL;
        $command =  'rpmbuild --define "_topdir `pwd`" -ba SPECS/'.$this->specFilename.'.spec';
        passthru($command, $returnValue);
        chdir($startDirectory);
        
        if ($returnValue != 0) {
            throw new BastionException("rpmbuild didn't return 0 - presumably something went wrong.");
        }
    }

    /**
     * @param $repoDirectory
     */
    function copyPackagesToRepoDir($repoDirectory) {
        $filePatterns = [
            $this->buildDir.'/RPMS/noarch/*.rpm' => 'RPMS/noarch',
            $this->buildDir.'/RPMS/x86_64/*.rpm' => 'RPMS/x86_64',
            $this->buildDir.'/SRPMS/*.rpm'       => 'SRPMS',
        ];
        
        foreach ($filePatterns as $sourcePattern => $destDirectory) {
            $files = glob($sourcePattern);
            var_dump($files);
            
            echo "Need to copy to ".$repoDirectory.'/'.$destDirectory;
            exit(0);
        }
    }
}