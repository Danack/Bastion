<?php


namespace Bastion\RPM;


class RPMConfigException extends \Exception {

    private $errors;

    function __construct($message, array $errors, $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    function getErrors() {
        return $this->errors;
    }
}

 