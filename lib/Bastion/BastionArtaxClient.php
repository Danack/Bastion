<?php


namespace Bastion;

use Amp\Artax\Client as ArtaxClient;

use Amp\Reactor;
use Amp\ReactorFactory;
use Bastion\Config;
use Amp\Artax\Cookie\CookieJar;
use Amp\Artax\HttpSocketPool;
//use Amp\Acesync\Encryptor;
use Nbsock\Encryptor;
use Amp\Artax\WriterFactory;

use Danack\Console\Output\OutputInterface;
use Psr\Log\LogLevel;



class BastionArtaxClient extends ArtaxClient  {


    private $progressDisplay;

    /**
     * @var OutputLogger
     */
    private $output;
    

    public function __construct(
        OutputLogger $output,
        \Bastion\Progress $progressDisplay,
        Reactor $reactor = null,
        CookieJar $cookieJar = null,
        HttpSocketPool $socketPool = null,
        Encryptor $encryptor = null,
        WriterFactory $writerFactory = null) {
        parent::__construct($reactor, $cookieJar, $socketPool, $encryptor, $writerFactory);

        $this->progressDisplay = $progressDisplay;
        $this->output = $output;
    }

//    /**
//     * Prevent duplicate requests to URIs. I'm not convinced this is the correct way to
//     * do it...shouldn't it be on the apiService?
//     * It could also be done by querying whether the request has already been done in
//     * ArtifactFetcher....which also doesn't seem a good place to have it.
//     * @var array
//     */
//    private $requestedURIs = [];


    /**
     * @param $uriOrRequest
     * @param array $options
     * @return \Amp\Promise
     */
    public function request($uriOrRequest, array $options = []) {
        $displayText = "Making request: ";

        if (is_string($uriOrRequest)) {
            $displayText .= "string: ". $uriOrRequest;

//            if (array_key_exists($uriOrRequest, $this->requestedURIs) == true) {
//                //Skipit
//                return null;
//            }
//
//            $this->requestedURIs[$uriOrRequest] = true;
        }
        else if ($uriOrRequest instanceof \Amp\Artax\Request) {
            $displayText .= "Request uri: ".$uriOrRequest->getUri();

//            $uri = $uriOrRequest->getUri();
//            if (array_key_exists($uri, $this->requestedURIs) == true) {
//                //Skipit
//                return null;
//            }
//            $this->requestedURIs[$uri] = true;
        }
        else {
            $displayText .= "class ".get_class($uriOrRequest);
        }

        $this->output->write($displayText, LogLevel::INFO);

        $watchCallback = $this->progressDisplay->getWatcher($uriOrRequest);
        $promise = parent::request($uriOrRequest);
        $progress = new \Amp\Artax\Progress($watchCallback);
        $promise->watch($progress);

        return $promise;
    }
}

 