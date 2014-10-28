<?php


namespace Bastion;

use Danack\Console\Output\OutputInterface;
use Psr\Log\LogLevel;



class OutputLogger {

    /**
     * @var OutputInterface
     */
    private $output;


    const INFO = 'info';
    const NOTICE = 'notice';
    const WARNING = 'warn';
    const ERROR = 'error';

    /**
     * @var array
     */
    private $verbosityLevelMap = array(
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
    );
    /**
     * @var array
     */
    private $formatLevelMap = array(
        LogLevel::EMERGENCY => self::ERROR,
        LogLevel::ALERT => self::ERROR,
        LogLevel::CRITICAL => self::ERROR,
        LogLevel::ERROR => self::ERROR,
        LogLevel::WARNING => self::WARNING,
        LogLevel::NOTICE => self::NOTICE,
        LogLevel::INFO => self::INFO,
        LogLevel::DEBUG => self::INFO,
    );



    function __construct(OutputInterface $output) {
        $this->output = $output;
    }


    /**
     * @param $message
     * @param $logLevel
     */
    function write($message, $logLevel = LogLevel::NOTICE) {
        $type = self::INFO;
        if (isset($this->formatLevelMap[$logLevel])) {
            $type = $this->formatLevelMap[$logLevel];
        }

        if ($this->output->getVerbosity() >= $this->verbosityLevelMap[$logLevel]) {
            $output = sprintf('<%s>[%s] %s</%s>', $type, $logLevel, $message, $type);
            $this->output->writeln($output);
        }
    }
}

