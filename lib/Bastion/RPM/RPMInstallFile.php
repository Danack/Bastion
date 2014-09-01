<?php

namespace Bastion\RPM;

class RPMInstallFile {

    private $sourceFilename;
    private $destFilename;

    function __construct($sourceFilename, $destFilename) {
        $this->sourceFilename = $sourceFilename;
        $this->destFilename = $destFilename;
    }

    /**
     * @return mixed
     */
    public function getDestFilename() {
        return $this->destFilename;
    }

    /**
     * @return mixed
     */
    public function getSourceFilename() {
        return $this->sourceFilename;
    }
}


 