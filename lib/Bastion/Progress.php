<?php


namespace Bastion;


use Artax\Request;

class Progress {

    private $displayLines = [];
    private $watcherID = 0;
    private $expireTimes = [];
    
    function clearScreen() {
        print chr(27) . "[2J" . chr(27) . "[;H"; // clear screen
        echo 'Progress:', PHP_EOL;
        echo '------------------------------------', PHP_EOL, PHP_EOL;
    }

    function removeExpiredItems() {
        $now = time();

        foreach ($this->expireTimes as $watcherID => $expireTime) {
            if ($now > $expireTime) {
                unset($this->displayLines[$watcherID]);
                unset($this->expireTimes[$watcherID]);
            }
        }
    }

    function render() {
        $this->clearScreen();
        $this->removeExpiredItems();
        
        echo implode($this->displayLines, PHP_EOL), PHP_EOL;
    }

    function displayStatus($text, $ttl = 2) {
        $nextWatcherID = $this->nextWatcherID();
        $this->expireTimes[$nextWatcherID] = time() + $ttl;
        $this->displayLines[$nextWatcherID] = $text;
        //$this->render();
        echo $text.PHP_EOL;
    }



    function nextWatcherID() {
        $return = $this->watcherID;
        $this->watcherID++;
        
        return $return;
    }
    
    public function getWatcher($uriOrRequest) {
        
        $watcherID = $this->nextWatcherID();

        if ($uriOrRequest instanceof Request) {
            $uri = $uriOrRequest->getUri();
        }
        else {
            $uri = $uriOrRequest;
        }
        
        
        $callback = function($update) use ($watcherID, $uri) {
            $this->observe($update, $watcherID, $uri);
        };
        
        return $callback;
    }
    
    public function observe($update, $watcherID, $uri) {

        //echo "ID = $watcherID ".$update['bar'].PHP_EOL;
        
//        var_dump($update);
//        'sock_procured_at' => $this->socketProcuredAt,
//            'redirect_count' => $this->redirectCount,
//            'bytes_rcvd' => $this->bytesRcvd,
//            'header_bytes' => $this->headerBytes,
//            'content_length' => $this->contentLength,
//            'percent_complete' => $this->percentComplete,
//            'bytes_per_second' => $this->bytesPerSecond,
//            'is_request_sent' => (bool) $this->isRequestSendComplete,
//            'is_complete' => (bool) $this->isComplete,
//            'is_error' =>(bool)  $this->isError,
//            'bar' => $bar

        
        if ($update['is_request_sent'] == false) {
            return;
        }
        
        if(substr($update['bar'], 0, 4) === '[DET') {
            return;
        }

        if (strpos($update['bar'], 'SIZE UNKNOWN') !== false) {
            return;
        }
        
//        if ($update['isRequestSendComplete'] == false) {
//            return;
//        }

        
        
        if ($update['is_complete']) {
            unset($this->expireTimes[$watcherID]);
            unset($this->displayLines[$watcherID]);
            return;
        }
        
        if ($update['is_error']) {
            $this->expireTimes[$watcherID] = time() + 1;
        }

        $this->displayLines[$watcherID] = $update['bar'];
        echo $watcherID.$update['bar'].$uri.PHP_EOL;
        //$this->render();
    }
}

 