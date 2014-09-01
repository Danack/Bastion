<?php

namespace Bastion\RPM;


class RPMDataDirectory {

    private $mode;
    private $user;
    private $group;
    private $directory;

    function __construct($mode, $user, $group, $directory) {
        $this->mode = $mode;
        $this->user = $user;
        $this->group = $group;
        $this->directory = $directory;
    }

    /**
     * @return mixed
     */
    public function getDirectory() {
        return $this->directory;
    }

    /**
     * @return mixed
     */
    public function getGroup() {
        return $this->group;
    }

    /**
     * @return mixed
     */
    public function getMode() {
        return $this->mode;
    }

    /**
     * @return mixed
     */
    public function getUser() {
        return $this->user;
    }
}