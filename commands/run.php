<?php
$usage = <<<'DOCOPT'
Run traces defined for a project in a caperfile

Usage: run [--show-class-names] [<script>...]

Reads configuration from caper.yml (see `caper help caperfile`
for more information) and runs 

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
    if ($options['<script>']) {
        foreach ($options['<script>'] as $script) {
            $cli->bold()->cyan()->out("Running script: $script")->br();
            $runner->run($config, $script);
        }
    }
    else {
        $cli->bold()->cyan()->out("Running all scripts")->br();
        $runner->runAll($config);
    }
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
