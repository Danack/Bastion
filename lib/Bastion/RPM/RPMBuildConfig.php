<?php

namespace Bastion\RPM;



class RPMBuildConfig {

    /** @var  \Bastion\RPM\RPMInstallFile[] */
    private $rpmInstallFiles;

    /**
     * @var \Bastion\RPM\RPMDataDirectory[]
     */
    private $rpmDataDirectories;
    /** @var array */
    private $crontabFiles;
    private $sourceFiles;
    private $sourceDirectories;
    
    public $scripts;

    private $unixUser = 'www-data';
    private $unixGroup = 'www-data';
    

    function __construct(
        array $rpmInstallFiles,
        array $rpmDataDirectories,
        array $crontabFiles,
        array $sourceFiles,
        array $sourceDirectories,
        array $scripts
    ) {
        $this->rpmInstallFiles = $rpmInstallFiles;
        $this->rpmDataDirectories = $rpmDataDirectories;
        $this->crontabFiles = $crontabFiles;
        $this->sourceFiles = $sourceFiles;
        $this->sourceDirectories = $sourceDirectories;
        $this->scripts = $scripts;
    }

    function checkData() {
        //TODO - implement
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
    public function getScripts() {
        return $this->scripts;
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


    public static function fromConfig($config) {
        $rpmInstallFiles = self::parseInstallFiles($config);
        $rpmDataDirectories = self::parseDataDirectores($config);
        $crontabFiles = self::parseCrontabFiles($config);
        
        $srcFiles = [];
        if(isset($config['srcFiles'])) {
            $srcFiles = $config['srcFiles'];
        }

        $srcDirectories = [];
        if(isset($config['srcDirectories'])) {
            $srcDirectories = $config['srcDirectories'];
        }

        $scripts = [];
        if(isset($config['scripts'])) {
            $scripts = $config['scripts'];
        }

        $instance = new RPMBuildConfig(
            $rpmInstallFiles,
            $rpmDataDirectories,
            $crontabFiles,
            $srcFiles,
            $srcDirectories,
            $scripts
        );

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
        return $this->unixGroup;
    }

    /**
     * @param string $unixGroup
     */
    public function setUnixGroup($unixGroup) {
        $this->unixGroup = $unixGroup;
    }
}

