<?php


namespace Bastion\RPM;

use Bastion\BastionException;


/**
 * Class RootFileBuildConfigProvider 
 * The standard BuildConfigProvider. It creates a RPMBuildConfig by reading the file 
 * `bastion.php` in the root directory of the project that is being RPM'd.
 * @package Bastion\RPM
 */
class RootFileBuildConfigProvider implements BuildConfigProvider {

    /**
     * @param $sourceDirectory
     * @return RPMBuildConfig
     * @throws BastionException
     */
    function getBuildConfig($sourceDirectory) {
        $bastionConfigFilename = $sourceDirectory.'/bastion.php';
        $bastionConfig = include_once $bastionConfigFilename;
        if ($bastionConfig == false) {
            throw new BastionException("Could not read build config file $bastionConfigFilename.");
        }
        $buildConfig = RPMBuildConfig::fromConfig($bastionConfig);

        return $buildConfig;
    }
}

 