<?php

namespace Bastion\RPM;

class RPMBuildConfig {

    /** @var  \Bastion\RPM\RPMInstallFile[] */
    private $rpmInstallFiles;

    /**
     * @var \Bastion\RPM\RPMDataDirectory[]
     */
    private $rpmDataDirectories;
    private $crontabFiles;
    private $sourceFiles;
    private $sourceDirectories;

    function __construct(
        array $rpmInstallFiles,
        array $rpmDataDirectories,
        array $crontabFiles,
        array $sourceFiles,
        array $sourceDirectories
    ) {
        $this->rpmInstallFiles = $rpmInstallFiles;
        $this->rpmDataDirectories = $rpmDataDirectories;
        $this->crontabFiles = $crontabFiles;
        $this->sourceFiles = $sourceFiles;
        $this->sourceDirectories = $sourceDirectories;
    }

    /**
     * @return array
     */
    public function getCrontabFiles() {
        return $this->crontabFiles;
    }

    /**
     * @return \Bastion\RPM\RPMDataDirectory[]
     */
    public function getRPMDataDirectories() {
        return $this->rpmDataDirectories;
    }

    /**
     * @return RPMInstallFile[]
     */
    public function getRPMInstallFiles() {
        return $this->rpmInstallFiles;
    }

    
    
    /**
     * @return array
     */
    public function getSourceDirectories() {
        return $this->sourceDirectories;
    }

    /**
     * @return \Bastion\RPM\RPMInstallFile[]
     */
    public function getSourceFiles() {
        return $this->sourceFiles;
    }
}
 