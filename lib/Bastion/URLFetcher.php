<?php


namespace Bastion;


class URLFetcher {
    
    private $accessToken;

    /**
     * @var \Amp\Artax\Client
     */
    private $client;
    
    function __construct(\Amp\Artax\Client $client, $accessToken) {
        $this->accessToken = $accessToken;
        $this->client = $client;
    }

    /**
     * @param $uri
     * @param callable $responseCallback
     */
    function downloadFile($uri, callable $responseCallback) {
        $request = new \Amp\Artax\Request();
        $request->setUri($uri);
        $request->setHeader("User-Agent", "Danack/Bastion");
        if ($this->accessToken) {
            $request->setHeader("Authorization", "token ".$this->accessToken);
        }

        $promise = $this->client->request($request);
        $promise->when($responseCallback);
    }
}

 