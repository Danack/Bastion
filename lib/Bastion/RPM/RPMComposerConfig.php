<?php


namespace Bastion\RPM;

use Bastion\BastionException;
use JsonSchema\Validator as JsonValidator;
use Composer\Json\JsonValidationException;

class RPMComposerConfig {

    private $name = null;
    private $version = null;
    private $release = '1';
    private $summary = null;
    private $fullDescription = null;
    private $architecture = 'noarch';
    private $rpmGroup = "Internet Applications";
    private $license = 'None';

    //@todo - read scripts from composer.json, or possibly
    //decide against ever implementing these.

    /**
     * @param $filename
     * @throws BastionException
     * @throws JsonValidationException
     */
    function readComposerJsonFile($filename) {
        $composerContents = @file_get_contents($filename);
        if ($composerContents === false) {
            throw new BastionException("Could not read composer json file `$filename`.");
        }

        $composerObject = @json_decode($composerContents);
        if ($composerContents == null) {
            throw new BastionException("Could not decode composer json file `$filename`, this is usually due to non-UTF8 characters.");
        }

        $this->checkComposerJson($composerObject, $filename);
        $this->initialiseFromData(json_decode($composerContents, true));
    }

    /**
     * @throws RPMConfigException
     */
    public function checkData() {
        $errors = [];
        if ($this->name == null) {
            $errors[] = 'name is null, it must be set in the projectConfig';
        }
        if ($this->version == null) {
            $errors[] = 'version is null, it must be set in the projectConfig';
        }
        if ($this->summary == null) {
            $errors[] = 'summary is null, it must be set in the projectConfig';
        }
        if ($this->fullDescription == null) {
            $errors[] = 'fullDescription is null, it must be set in the projectConfig';
        }

        if (count($errors)) {
            throw new RPMConfigException("Errors found in RPMProjectConfig", $errors);
        }
    }

    /**
     * @param $composerData
     */
    public function initialiseFromData($composerData) {
        $this->fullDescription = $composerData['description'];
        $this->summary  = $composerData['description'];
        
        $lastSlashPosition = strrpos($composerData['name'], '/');
        
        if ($lastSlashPosition !== false) {
            $this->name = substr($composerData['name'], $lastSlashPosition + 1);
        }
        else {
            $this->name = $composerData['name'];
        }

        if (isset($composerData['version'])) {
            $this->version = $composerData['version'];
        }

        if (isset($composerData['license'])) {
            $this->license = $composerData['license'];
            if (is_array($this->license) == true) {
                //Composer licesnse support an array of license types,
                //normalise the data to a string.
                $this->license = implode(', ', $this->license);
            }
        }
    }

    /**
     * @param $composerContents
     * @param $filename
     * @throws JsonValidationException
     */
    function checkComposerJson($composerContents, $filename) {
        $validator = new JsonValidator();
        $schemaFile = __DIR__ . '/../../../vendor/composer/composer/res/composer-schema.json';
        $schemaData = json_decode(file_get_contents($schemaFile));
        
        $validator->check($composerContents, $schemaData);
        if (!$validator->isValid()) {
            $errors = array();
            foreach ((array) $validator->getErrors() as $error) {
                $errors[] = ($error['property'] ? $error['property'].' : ' : '').$error['message'];
            }
            throw new JsonValidationException('"'.$filename.'" does not match the expected JSON schema', $errors);
        }
    }
    
    function getName() {
        return $this->name;
    }
    
    function getVersion() {
        return $this->version;
    }
    
    function getUnmangledVersion() {
        return $this->getVersion();
    }
    
    function getRelease() {
        return $this->release;
    }

    function getSummary() {
        return $this->summary;
    }

    function getFullDescription() {
        return $this->fullDescription;
    }

    function getArch() {
        return $this->architecture;
    }


    function getRPMGroup() {
        return $this->rpmGroup;
    }
    
    /**
     * @param string $architecture
     */
    public function setArchitecture($architecture) {
        $this->architecture = $architecture;
    }

    /**
     * @param string $fullDescription
     */
    public function setFullDescription($fullDescription) {
        $this->fullDescription = $fullDescription;
    }

    /**
     * @param null $license
     */
    public function setLicense($license) {
        $this->license = $license;
    }

    /**
     * @param null $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @param string $release
     */
    public function setRelease($release) {
        $this->release = $release;
    }
    
    /**
     * @param string $summary
     */
    public function setSummary($summary) {
        $this->summary = $summary;
    }

    /**
     * @param null $version
     */
    public function setVersion($version) {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getArchitecture() {
        return $this->architecture;
    }

    /**
     * @return null
     */
    public function getLicense() {
        return $this->license;
    }
    
    
}

 