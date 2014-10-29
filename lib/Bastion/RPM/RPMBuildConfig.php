<?php

namespace Bastion\RPM;



class RPMBuildConfig {

    /** @var  \Bastion\RPM\RPMInstallFile[] */
    private $rpmInstallFiles = [];

    /**
     * @var \Bastion\RPM\RPMDataDirectory[]
     */
    private $rpmDataDirectories = [];

    /** @var array */
    private $crontabFiles = [];
    private $sourceFiles = [];
    private $sourceDirectories = [];


    
    /**
     * @var array The scripts that should be run after the composer install
     * when the rpm is being generated
     */
    public $buildScripts = [];


    /** @var array The scripts that should be run before the package is installed. */
    public $preInstallScripts = [];
    
    /** @var array The scripts that should be run after the package has been installed. */
    public $postInstallScripts = [];

    /** @var array The scripts that should be run before the package is uninstalled. */
    public $preUninstallScripts = [];
    
    /** @var array The scripts that should be run after the package has been uninstalled. */
    public $postUninstallScripts = [];
    
    
    /** @var string Which unix user the files should be installed as. */
    private $unixUser = 'root';
    /** @var null Which unix group the files should be installas as. They are installed with
     * the same group as the unixUser if $unixGroup is null. */
    private $unixGroup = null;

    /**
     * @var string The directory to install to. Defaults to /home/$name/$name
     */
    private $installDir;

    function getInstallDir() {
        return $this->installDir;
    }

    /**
     * @param $config
     * @return \Bastion\RPM\RPMInstallFile[]
     */
    static function parseInstallFiles($config) {
        $result = [];
        if (isset($config['installFies'])) {
            foreach ($config['installFies'] as $installFileEntry) {
                $result[] = new RPMInstallFile($installFileEntry['src'], $installFileEntry['dest']);
            }
        }

        return $result;
    }


    /**
     * @param $config
     * @return array
     */
    static function parseDataDirectores($config) {
        $dataDirectories = [];
        if (isset($config['dataDirectories'])) {
            foreach ($config['dataDirectories'] as $dataDirectory) {
                list($mode, $user, $group, $directoryName) = $dataDirectory;
                $dataDirectories[] = new RPMDataDirectory($mode, $user, $group, $directoryName);
            }
        }

        return $dataDirectories;
    }

    /**
     * @param $config
     * @return array
     */
    static function parseCrontabFiles($config) {
        if(isset($config['crontabFiles'])) {
            return $config['crontabFiles'];
        }

        return [];
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
     * @return mixed
     */
    public function getBuildScripts() {
        return $this->buildScripts;
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


    /**
     * @param $config
     * @return RPMBuildConfig
     */
    public static function fromConfig($config) {
        $instance = new self();
        $instance->rpmInstallFiles = self::parseInstallFiles($config);
        $instance->rpmDataDirectories = self::parseDataDirectores($config);
        $instance->crontabFiles = self::parseCrontabFiles($config);

        if(isset($config['installDir'])) {
            $instance->installDir = $config['installDir'];
        }
        
        if(isset($config['srcFiles'])) {
            $instance->sourceFiles = $config['srcFiles'];
        }

        if(isset($config['srcDirectories'])) {
            $instance->sourceDirectories = $config['srcDirectories'];
        }
        
        if(isset($config['scripts'])) {
            $instance->buildScripts = $config['scripts'];
        }

        if(isset($config['postInstallScripts'])) {
            $instance->postInstallScripts = $config['postInstallScripts'];
        }

        return $instance;
    }

    /**
     * @return string
     */
    public function getUnixUser() {
        return $this->unixUser;
    }

    /**
     * @param string $unixUser
     */
    public function setUnixUser($unixUser) {
        $this->unixUser = $unixUser;
    }

    /**
     * @return string
     */
    public function getUnixGroup() {
        if ($this->unixGroup == null) {
            return $this->getUnixUser();
        }

        return $this->unixGroup;
    }

    /**
     * @param string $unixGroup
     */
    public function setUnixGroup($unixGroup) {
        $this->unixGroup = $unixGroup;
    }
}

