<?php


namespace Bastion;


use Amp\Artax\Request;

use Psr\Log\LogLevel;

class Progress {

    private $displayLines = [];
    private $watcherID = 0;
    private $expireTimes = [];

    /**
     * @var OutputLogger
     */
    private $output;

    /**
     * @param OutputLogger $output
     */
    function __construct(OutputLogger $output) {
        $this->output = $output;
    }

    /**
     * 
     */
    function clearScreen() {
        print chr(27) . "[2J" . chr(27) . "[;H"; // clear screen
        echo 'Progress:', PHP_EOL;
        echo '------------------------------------', PHP_EOL, PHP_EOL;
    }

    /**
     * 
     */
    function removeExpiredItems() {
        $now = time();

        foreach ($this->expireTimes as $watcherID => $expireTime) {
            if ($now > $expireTime) {
                unset($this->displayLines[$watcherID]);
                unset($this->expireTimes[$watcherID]);
            }
        }
    }

    /**
     * 
     */
    function render() {
        $this->clearScreen();
        $this->removeExpiredItems();
        
        echo implode($this->displayLines, PHP_EOL), PHP_EOL;
    }

    /**
     * @param $text
     * @param int $ttl
     */
    function displayStatus($text, $ttl = 2) {
        $nextWatcherID = $this->nextWatcherID();
        $this->expireTimes[$nextWatcherID] = time() + $ttl;
        $this->displayLines[$nextWatcherID] = $text;
        //$this->render();
        echo $text.PHP_EOL;
    }


    /**
     * @return int
     */
    function nextWatcherID() {
        $return = $this->watcherID;
        $this->watcherID++;
        
        return $return;
    }

    /**
     * @param $uriOrRequest
     * @return callable
     */
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

    /**
     * @param $update
     * @param $watcherID
     * @param $uri
     */
    public function observe($update, $watcherID, $uri) {
        if ($update['request_state'] < \Amp\Artax\Progress::SENDING_REQUEST) {
            return;
        }

        switch ($update['request_state']) {
            case(\Amp\Artax\Progress::CONNECTING): {
                $message = "Connecting";
                break;
            }
            case(\Amp\Artax\Progress::SENDING_REQUEST): {
                $message = "Sending request";
                break;
            }
            case(\Amp\Artax\Progress::AWAITING_RESPONSE): {
                $message = "Awaiting response";
                break;
            }
            case(\Amp\Artax\Progress::REDIRECTING): {
                $message = "Reading REDIRECTING";
                break;
            }
            case(\Amp\Artax\Progress::READING_LENGTH): {
                $message = "Reading length";
                break;
            }
            case(\Amp\Artax\Progress::READING_UNKNOWN): {
                $message = "Reading unknown";
                break;
            }
            case(\Amp\Artax\Progress::COMPLETE): {
                $message = "Complete";
                break;
            }
            case(\Amp\Artax\Progress::ERROR): {
                $message = "Error";
                break;
            }

            default: {
                $message = "Unknown state ".$update['request_state'];
            }
        }

        $message =  "$watcherID $message for $uri";
        $this->output->write($message, LogLevel::INFO);
        
        
//        if ($update['request_state'] >= \Amp\Artax\Progress::COMPLETE) {
//            //delete the bar.
//            return;
//        }
//
//        if (isset($update['fraction_complete'])) {
//            $percentComplete = intval(100 * $update['fraction_complete']);
////            echo $watcherID.' '.$percentComplete.'% '.$update['request_state'].' '.time().' '.$uri.PHP_EOL;
//        }
//        else {
//  //          echo $watcherID.' '.$update['bytes_rcvd'].' bytes '.$update['request_state'].' '.time().' '.$uri.PHP_EOL;
//        }
//        
    }
}

 