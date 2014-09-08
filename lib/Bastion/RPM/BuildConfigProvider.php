<?php

namespace Bastion\RPM;


/**
 * Interface BuildConfigProvider The RPMBuildConfig will almost always require on some information
 * from the extracted package. Being able to define a custom BuildConfigProvider allows you to
 * have it built in a different way than just being in the root directory of the project.  
 * @package Bastion\RPM
 */
interface BuildConfigProvider {
    function getBuildConfig($sourceDirectory);
}



 