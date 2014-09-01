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
    private $installDirDest;

    function __construct(RPMBuildConfig $buildConfig, RPMProjectConfig $projectConfig) {
        $this->buildConfig = $buildConfig;
        $this->projectConfig = $projectConfig;

        $this->installDir = "/home/intahwebz/intahwebz-1.2.3/";
        $this->installDirDest = "\$RPM_BUILD_ROOT/home/intahwebz/intahwebz-1.2.3/";

        $this->filesMacro = "%files
%defattr(555,intahwebz,www-data)\n";
    }


    private function addInstallFiles() {
        foreach ($this->buildConfig->getRPMInstallFiles() as $file) {
            $src = $file->getSourceFilename();
            $dest = $file->getDestFilename();
            $dirname = dirname($dest);
            $this->buildScript .= "mkdir -p \$RPM_BUILD_ROOT/$dirname\n";
            $this->buildScript .= "cp \$RPM_BUILD_DIR/$src \$RPM_BUILD_ROOT/$dest \n";
            //todo - adjut attr
            $this->filesMacro .= "$dest\n";
        }
    }


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

    function addDataDirectories() {
        foreach ($this->buildConfig->getRPMDataDirectories() as $rpmDataDirectory) {
            $modeString = "-";
            $mode = $rpmDataDirectory->getMode();
            if ($mode !== false) {
                $modeString = sprintf("0%o", $mode);
            }

            $this->buildScript .= sprintf(
                "mkdir -p \$RPM_BUILD_ROOT/%s/%s \n",
                $this->installDir,
                $rpmDataDirectory->getDirectory()
            );

            $this->filesMacro .= sprintf(
                "%%attr(%s, %s, %s) %%dir %s/%s\n",
                $modeString,
                $rpmDataDirectory->getUser(),
                $rpmDataDirectory->getGroup(),
                $this->installDir,
                $rpmDataDirectory->getDirectory()
            );
        }
    }

    function addSourceDirectories() {
        foreach ($this->buildConfig->getSourceDirectories() as $srcDirectory) {
            $this->filesMacro .= sprintf(
                "%s/%s/*\n",
                $this->installDir,
                $srcDirectory
            );

            $this->buildScript .= sprintf(
                "mkdir -p \$RPM_BUILD_ROOT/%s/%s \n",
                $this->installDir,
                $srcDirectory
            );

            $this->buildScript .= sprintf(
                "cp -R \$RPM_BUILD_DIR/%s/* \$RPM_BUILD_ROOT/%s/%s/ \n",
                $srcDirectory, 
                $this->installDir,
                $srcDirectory
            );
        }
    }

    function addSourceFiles() {
        foreach ($this->buildConfig->getSourceFiles() as $srcFile) {
            $this->filesMacro .= sprintf(
                "%s/%s\n",
                $this->installDir,
                $srcFile
            );
            
            $this->buildScript .= sprintf(
                "cp \$RPM_BUILD_DIR/%s \$RPM_BUILD_ROOT/%s/%s \n",
                $srcFile,
                $this->installDir,
                $srcFile
            );
        }
    }

    function generateSpec() {
        $projectName = $this->projectConfig->getName();
        $version = $this->projectConfig->getVersion();
        $unmangledVersion = $this->projectConfig->getUnmangledVersion();
        $release = $this->projectConfig->getRelease();
        $summary = $this->projectConfig->getSummary();

        //$prepScript = "%setup -n %{name}-%{unmangled_version} -n %{name}-%{unmangled_version}";
        $dir = "/home/intahwebz";
        $blah = "intahwebz-1.2.3";
        $prepScript = sprintf(
            "%%{__mkdir} -p \$RPM_BUILD_ROOT%s/%s",
            $dir,
            $blah
        );

        $installScript = "";
    
        $this->processCrontab();
        $this->addInstallFiles();
        $this->addDataDirectories();
        $this->addSourceDirectories();
        $this->addSourceFiles();

        $license = null;
        $licenseString = "License: None";
    
        if ($license) {
            //@TODO - get from project config
            $licenseString = "License: $license)";
        }

        $fullDescription = $this->projectConfig->getFullDescription();
        $cleanScript = "rm -rf \$RPM_BUILD_ROOT";

        $buildScript = $this->buildScript;
        $filesMacro = $this->filesMacro;

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
Group: Development/Libraries
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-buildroot
Prefix: %{_prefix}
BuildArch: noarch
AutoReqProv: no

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