<?php

namespace Bastion\RPM;

class SpecBuilder {

    /** @var RPMBuildConfig  */
    private $buildConfig;
    /** @var RPMProjectConfig  */
    private $projectConfig;

    private $filesMacro = '';
    private $buildScript = '';
    private $installDir;

    function __construct(RPMBuildConfig $buildConfig, RPMProjectConfig $projectConfig) {
        $buildConfig->checkData();
        $projectConfig->checkData();
        //cloning prevents mutability
        $this->buildConfig = clone $buildConfig;
        $this->projectConfig = clone $projectConfig;
        $this->installDir = $projectConfig->getInstallDir();

        $this->filesMacro = sprintf(
            "%%files
%%defattr(555,%s,%s)\n",
            $projectConfig->getUnixUser(),
            $projectConfig->getUnixGroup()
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




//        /usr/bin/getent group myservice || /usr/sbin/groupadd -r myservice
//        /usr/bin/getent passwd myservice || /usr/sbin/useradd -r -d /path/to/program -s /sbin/nologin myservice

        //Requires(pre): /usr/sbin/useradd, /usr/bin/getent        
//Requires(postun): /usr/sbin/userdel
// /usr/bin/getent passwd %s || /usr/sbin/useradd -r -d /path/to/program -s /sbin/nologin myservice\n",
        
   $preScript = sprintf(
       "/usr/bin/getent group %s || groupadd -r %s
/usr/bin/getent passwd %s || useradd --key UMASK=0022 -m -g %s %s",
       $this->projectConfig->getUnixGroup(),
       $this->projectConfig->getUnixGroup(),
       $this->projectConfig->getUnixUser(),
       $this->projectConfig->getUnixGroup(),
       $this->projectConfig->getUnixUser()

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
}