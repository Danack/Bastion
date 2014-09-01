<?php


namespace Bastion\RPM;


class RPMProjectConfig {

    function getName() {
        return 'intahwebz';
    }
    
    function getVersion() {
        return '1.2.3';
    }
    
    function getUnmangledVersion() {
        return $this->getVersion();
    }
    
    function getRelease() {
        return '1';
    }

    function getSummary() {
        return "Teh intahwebz";
    }

    function getFullDescription() {
        return "This is my website";
    }
}

 