#!/usr/bin/env php
<?php

use Danack\Console\Output\BufferedOutput;
use Danack\Console\Formatter\OutputFormatterStyle;
use Danack\Console\Helper\QuestionHelper;

if (!ini_get('allow_url_fopen')) {
    echo "allow_url_fopen is not enabled, Composer won't be able to run will probably break.\n";
    echo "php -d allow_url_fopen=1 \n";
}

require __DIR__.'/../src/bootstrap.php';

ini_set('memory_limit','512M');

$console = createConsole();

try {
    //Figure out what Command was requested.
    $parsedCommand = $console->parseCommandLine();
}
catch(\Exception $e) {
    //@TODO change to just catch parseException when that's implemented 
    $output = new BufferedOutput();
    $console->renderException($e, $output);
    echo $output->fetch();
    exit(-1);
}


//Run the command requested, or the help callable if no command was input
try {
    $output = $parsedCommand->getOutput();
    $formatter = $output->getFormatter();
    $formatter->setStyle('question', new OutputFormatterStyle('blue'));
    $formatter->setStyle('info', new OutputFormatterStyle('blue'));

    $injector = createInjector();

    $questionHelper = new QuestionHelper();
    $questionHelper->setHelperSet($console->getHelperSet());


    $reactor = $injector->make('Amp\Reactor');
    
    $injector->alias('Danack\Console\Output\OutputInterface', get_class($output));
    $injector->share($output);
    $githubArtaxService = $injector->make('GithubService\GithubService');

    $input = $parsedCommand->getInput();

    $configGenerator = new Bastion\Config\DialogueConfigGenerator(
        $parsedCommand->getInput(),
        $parsedCommand->getOutput(),
        $questionHelper,
        $githubArtaxService
    );

    $config = getConfigOrGenerate(
        $parsedCommand->getInput(),
        $output,
        $console,
        $configGenerator
    );

    $injector = createInjector($config, $reactor);

    $injector->alias('Danack\Console\Output\OutputInterface', get_class($output));
    $injector->share($output);

    $keynames = formatKeyNames($parsedCommand->getParams());

    $injector->execute(
        $parsedCommand->getCallable(),
        $keynames
    );
}
catch(\Bastion\BastionException $be) {
    echo "Error running Bastion: ".$be->getMessage().PHP_EOL;
    exit(-1);
}
catch(\Exception $e) {
    echo "Unexpected exception of type ".get_class($e)." running Bastion: ".$e->getMessage().PHP_EOL;
    echo $e->getTraceAsString();
    exit(-2);
}


