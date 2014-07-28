<?php


namespace Bastion;

use Artax\Request;

class URLFetcher {
    
    private $accessToken;

    /**
     * @var \Artax\AsyncClient
     */
    private $client;
    
    function __construct(\Artax\AsyncClient $client, $accessToken) {
        $this->accessToken = $accessToken;
        $this->client = $client;
    }

    /**
     * @param $uri
     * @param callable $responseCallback
     */
    function downloadFile($uri, callable $responseCallback) {
        $request = new \Artax\Request();
        $request->setUri($uri);
        $request->setHeader("User-Agent", "Danack/Bastion");
        if ($this->accessToken) {
            $request->setHeader("Authorization", "token ".$this->accessToken);
        }

        // What to do if a request encounters an exceptional error
        $onError = function(\Exception $e, Request $request)  {
            printf(
                "URLFetcher %s failed (", get_class($e), ") : %s\n",
                $request->getUri(),
                $e->getMessage()
            );
        };

        $this->client->request($request, $responseCallback, $onError);
    }
}

 