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

    //        var_dump($update);
//        exit(0);

//        const CONNECTING = 0;
//        const SENDING_REQUEST = 1;
//        const AWAITING_RESPONSE = 2;
//        const REDIRECTING = 4;
//        const READING_LENGTH = 8;
//        const READING_UNKNOWN = 16;
//        const COMPLETE = 32;
//        const ERROR = 33;


    public function observe($update, $watcherID, $uri) {
        if ($update['request_state'] < \Artax\Progress::SENDING_REQUEST) {
            return;
        }

        if ($update['request_state'] >= \Artax\Progress::COMPLETE) {
            //delete the bar.
            return;
        }

        echo $watcherID.' '.$update['fraction_complete'].'% '.$update['request_state'].' '.time().' '.$uri.PHP_EOL;
    }
}

 