<?php


namespace Bastion;


class InvalidComposerFileException extends \Exception {
    
    const BAD_SEMVER_IN_FILE = 1;
    const BAD_SEMVER_NORMALIZED = 2;
    const MISSING_COMPOSER_JSON = 3;
    const COMPOSER_JSON_DECODE_FAILED = 4;
    //const COMPOSER_JSON_EMPTY = 5;
    const COMPOSER_JSON_MISSING_NAME = 6;

    private static $errorMessages = [
        1 => "Bad Semver %s in file",
        2 => "Bad semver %s after normalizing",
        3 => "Missing composer json",
        4 => "Composer.josn decode failed",
        //5 => "Decoded composer.json is empty",
        6 => "Composer.json missing project name",
    ];

    public static function fromBadSemver($semver) {
        $message = sprintf(
            self::$errorMessages[self::BAD_SEMVER_IN_FILE],
            $semver
        );

        return new InvalidComposerFileException($message, self::BAD_SEMVER_IN_FILE);
    }
    
    public static function fromBadNormalizedSemver($semver) {
        $message = sprintf(
            self::$errorMessages[self::BAD_SEMVER_NORMALIZED],
            $semver
        );

        return new InvalidComposerFileException($message, self::BAD_SEMVER_NORMALIZED);
    }


    public static function fromMissingComposer($semver) {
        $message = self::$errorMessages[self::MISSING_COMPOSER_JSON];
        return new InvalidComposerFileException($message, self::MISSING_COMPOSER_JSON);
    }

    
    public static function fromJsonDecodeFailed($semver) {
        $message = sprintf(
            self::$errorMessages[self::MISSING_COMPOSER_JSON],
            $semver
        );

        return new InvalidComposerFileException($message, self::MISSING_COMPOSER_JSON);
    }


    public static function fromMissingName($name) {
        $message = sprintf(
            self::$errorMessages[self::COMPOSER_JSON_MISSING_NAME],
            $name
        );

        return new InvalidComposerFileException($message, self::COMPOSER_JSON_MISSING_NAME);
    }
}

 