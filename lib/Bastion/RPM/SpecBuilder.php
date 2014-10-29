<?php

namespace Bastion\RPM;

use Bastion\BastionException;


class SpecBuilder {

    /** 
     * @var RPMBuildConfig  
     */
    private $buildConfig;
    
    /** 
     * @var RPMComposerConfig  
     */
    private $composerConfig;

    private $filesMacro = '';
    private $buildScript = '';
    private $buildDir;
    private $specFilename = 'bastionRPM';

    /**
     * @param RPMBuildConfig $buildConfig - Comes from bastion.php
     * @param RPMComposerConfig $projectConfig - Comes from composer.json
     * @param $buildDir
     * @throws RPMConfigException
     */
    function __construct(RPMBuildConfig $buildConfig, RPMComposerConfig $projectConfig, $buildDir) {
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
        $this->composerConfig = clone $projectConfig;

        $this->filesMacro = sprintf(
            "%%files
%%defattr(555,%s,%s)\n",
            $buildConfig->getUnixUser(),
            $buildConfig->getUnixGroup()
        );
    }

    /**
     * Calculate what the install directory should be. If it's set in bastion.php use
     * that, otherwise install into /home/projectname
     * 
     * @return string
     */
    function getInstallDir() {
        $installDir = $this->buildConfig->getInstallDir();

        if ($installDir) {
            return $installDir;
        }

        //@TODO - should this have the version in it? Probably not...
        //that would only be needed if we wanted to support installing multiple versions at once
        //which yum usually doesn't allow
        return sprintf(
            '/home/%s',
            $this->composerConfig->getName()
        );
    }
    
    
    /**
     * Add the install file to the list of files macro, and also copy them into 
     * the build dir.
     */
    private function addInstallFiles() {
        foreach ($this->buildConfig->getRPMInstallFiles() as $file) {
            $src = $file->getSourceFilename();
            $dest = $file->getDestFilename();
            $dirname = dirname($dest);
            $this->buildScript .= "echo 'Adding install files'".PHP_EOL;
            $this->buildScript .= "mkdir -p \$RPM_BUILD_ROOT/$dirname\n";
            $this->buildScript .= "cp \$RPM_BUILD_DIR/$src \$RPM_BUILD_ROOT/$dest \n";
            //todo - adjust attr
            $this->filesMacro .= "$dest\n";
        }
    }

    /**
     * Add any calls to post build scripts.
     */
    private function addPostBuildScripts() {
        $this->buildScript .= "echo 'addPostBuildScripts'".PHP_EOL;
        foreach ($this->buildConfig->getBuildScripts() as $script) {
            echo "Adding script: $script".PHP_EOL;
            $this->buildScript .= "echo 'Running script: ".addslashes($script)."';".PHP_EOL;
            $this->buildScript .=  $script."\n";
        }
    }


    /**
     * Process the crontab entries. Copy them to the crond.d directory 
     * and list them in the files macro.
     */
    private function processCrontab() {
        $crontabFiles = $this->buildConfig->getCrontabFiles();
        if (count($crontabFiles)) {
            $this->buildScript .= "echo 'processCrontab'".PHP_EOL;
            $this->buildScript .= "mkdir -p \$RPM_BUILD_ROOT/etc/crond.d\n";
        }

        foreach ($crontabFiles as $crontabEntry) {
            $this->buildScript .= "cp \$RPM_BUILD_DIR/$crontabEntry \$RPM_BUILD_ROOT/etc/crond.d\n";
            $filepart = basename($crontabEntry);
            $this->filesMacro .= "/etc/crond.d/$filepart\n";
        }
    }

    /**
     * Add commands to create data directories, and add list them in the
     * files macro.
     */
    function addDataDirectories() {
        $this->buildScript .= "echo 'addDataDirectories'".PHP_EOL;
        foreach ($this->buildConfig->getRPMDataDirectories() as $rpmDataDirectory) {
            $modeString = "-";
            $mode = $rpmDataDirectory->getMode();
            if ($mode !== false) {
                $modeString = sprintf("0%o", $mode);
            }

            $this->buildScript .= sprintf(
                "mkdir -p \$RPM_BUILD_ROOT/%s/%s \n",
                $this->getInstallDir(),
                $rpmDataDirectory->getDirectory()
            );

            $this->filesMacro .= sprintf(
                "%%attr(%s, %s, %s) %%dir %s/%s\n",
                $modeString,
                $rpmDataDirectory->getUser(),
                $rpmDataDirectory->getGroup(),
                $this->getInstallDir(),
                $rpmDataDirectory->getDirectory()
            );
        }
    }

    /**
     * Add the source directories. Copies them to the build directory
     * and lists them in the files macro.
     */
    function addSourceDirectories() {
        foreach ($this->buildConfig->getSourceDirectories() as $srcDirectory) {
            $this->filesMacro .= sprintf(
                "%s/%s/*\n",
                $this->getInstallDir(),
                $srcDirectory
            );

            $this->buildScript .= "echo 'addSourceDirectories'".PHP_EOL;
            $this->buildScript .= sprintf(
                "mkdir -p \$RPM_BUILD_ROOT/%s/%s \n",
                $this->getInstallDir(),
                $srcDirectory
            );

            $this->buildScript .= sprintf(
                "cp -R \$RPM_BUILD_DIR/%s/* \$RPM_BUILD_ROOT/%s/%s/ \n",
                $srcDirectory,
                $this->getInstallDir(),
                $srcDirectory
            );
        }
    }

    /**
     * Add the individual source files. Copies them to the build directory
     * and lists them in the files macro.
     */
    function addSourceFiles() {
        $this->buildScript .= "echo 'addSourceFiles'".PHP_EOL;
        foreach ($this->buildConfig->getSourceFiles() as $srcFile) {
            $this->filesMacro .= sprintf(
                "%s/%s\n",
                $this->getInstallDir(),
                $srcFile
            );
            
            $this->buildScript .= sprintf(
                "cp \$RPM_BUILD_DIR/%s \$RPM_BUILD_ROOT/%s/%s \n",
                $srcFile,
                $this->getInstallDir(),
                $srcFile
            );
        }
    }


    function getPreInstallScript() {
        return $this->getActualScripts($this->buildConfig->preInstallScripts);
    }
    
    /**
     * @Todo get the composer scripts.
     * @return string
     */
    function getPostInstallScript() {
        return $this->getActualScripts($this->buildConfig->postInstallScripts);
    }

    function getPreUninstallScript() {
        return $this->getActualScripts($this->buildConfig->preUninstallScripts);
    }
    
    function getPostUninstallScript() {
        return $this->getActualScripts($this->buildConfig->postUninstallScripts);
    }
    
    function getActualScripts($scripts) {
        $finalScript = '';
        $searchReplace = [
            '%INSTALL_DIR%' => $this->getInstallDir()
        ];

        foreach ($scripts as $script) {
            $nextScript = str_replace(
                array_keys($searchReplace),
                array_values($searchReplace),
                $script
            );
            $finalScript .= $nextScript;
        }

        return $finalScript;
    }

    /**
     * @param $sourceDirectory
     * @throws BastionException
     * @internal param $destDirectory
     */
    function copyPackageToBuild($sourceDirectory) {
        $destDirectory = $this->buildDir.'BUILD';
        \Bastion\copyDirectory($sourceDirectory, $destDirectory);
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
        $projectName = $this->composerConfig->getName();
        $version = $this->composerConfig->getVersion();
        $unmangledVersion = $this->composerConfig->getUnmangledVersion();
        $release = $this->composerConfig->getRelease();
        $summary = $this->composerConfig->getSummary();

        $prepScript = sprintf(
            "%%{__mkdir} -p \$RPM_BUILD_ROOT%s",
            $this->getInstallDir()
        );

        $postInstallScript = $this->getPostInstallScript();
        $postUninstallScript = $this->getPostInstallScript();

        $this->processCrontab();
        $this->addInstallFiles();
        $this->addDataDirectories();
        $this->addSourceDirectories();
        $this->addSourceFiles();

        $license = null;
        $licenseString = "License: None";
    
        if ($license = $this->composerConfig->getLicense()) {
            //@TODO - get from project config
            $licenseString = "License: $license)";
        }

        $fullDescription = $this->composerConfig->getFullDescription();
        $arch = $this->composerConfig->getArch();
        $group = $this->composerConfig->getRPMGroup();

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
%description
$fullDescription

%prep
$prepScript

%build
$buildScript

%install

#Before installation.
%pre
$preScript

#After installation.
%post
$postInstallScript

#Before erasure.
%preun

#After erasure.
%postun
$postUninstallScript

%clean
$cleanScript

$filesMacro

END;

        return $specContents;
    }

    /**
     * Runs the rpmbuild command.
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
            $fullDirectory = $repoDirectory.'/'.$destDirectory;
            @mkdir($fullDirectory, 0755, true);
            $files = glob($sourcePattern);
            foreach ($files as $file) {
                $newFilename = $fullDirectory.'/'.basename($file);
                $copied = @copy($file, $newFilename);
                if ($copied === false) {
                    echo "Failed to copy $file to $newFilename\n";
                }
            }
        }
    }
}
