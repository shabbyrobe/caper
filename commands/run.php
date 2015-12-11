<?php
$usage = <<<'DOCOPT'
Usage: run [--show-class-names]

DOCOPT;

$options = caper_opt_handle($usage);

$cwd = getcwd();
$configFile = "$cwd/caper.yml";
if (!file_exists($configFile)) {
    die("Config file not found\n");
}

$showClassNames = $options['--show-class-names'];

$config = \Caper\Config::fromYaml(file_get_contents($configFile), $cwd);
$runner = new \Caper\Trace\Runner();
$parser = new \Caper\Trace\Parser;

caper_header($cli);

scripts: {
    $cli->bold()->cyan()->out("Running scripts")->br();

    $runner->run($config);
}

parse: {
    $collector = new \Caper\Stack\Collector($config, $parser);
    $cli->br()->bold()->cyan()->out("Parsing trace (this may take a while)")->br(); 
    foreach ($runner->files as $file) {
        $collector->collect($file);
    }

    $cli->br()->bold()->cyan()->out("Fetching signatures")->br(); 
    $collector->loadSignatures();

    $result = $collector->result();
}

dump: {
    $dumper = new \Caper\Stack\Dumper($cli);
    $dumper->out($collector, $showClassNames);
}
