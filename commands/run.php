<?php
$usage = <<<'DOCOPT'
Usage: run

DOCOPT;

$options = (new \Docopt\Handler())->handle($usage);

$cwd = getcwd();
$configFile = "$cwd/caper.yml";
if (!file_exists($configFile)) {
    die("Config file not found\n");
}

$config = \Caper\Config::fromYaml(file_get_contents($configFile), $cwd);
$runner = new \Caper\Trace\Runner();
$parser = new \Caper\Trace\Parser;

caper_header();

scripts: {
    $cli->bold()->cyan()->out("Running scripts")->br();

    $runner->run($config);
}

parse: {
    $cli->br()->bold()->cyan()->out("Parsing trace (this may take a while)")->br(); 

    $functions = [];
    foreach ($runner->files as $traceFile) {
        foreach ($parser->stackIterator($traceFile) as list ($traceId, $call, $stack)) {
            $parsedName = \Caper\Filter::parseName($call->entry->function);

            if ($config->filter->isIncluded(...$parsedName)) {
                $functions[$call->entry->function][] = [$parsedName, $traceId, $call, $stack];
            }
        }
    }
}

collect: {
    $i = 0;
    $callCounts = [];
    foreach ($functions as $name => $calls) {
        foreach ($calls as $callIdx => $callInfo) {
            list ($parsedName, $traceId, $call, $stack) = $callInfo;
            $argv = $parser->parseArgs($call->entry->argv);

            $argAggregate = [];
            foreach ($argv as $arg) {
                if ($arg->type === 'object') {
                    $aggregate = $arg->kind;
                } elseif ($arg->type === 'bool') {
                    $aggregate = $arg->value ? 'true' : 'false';
                } elseif ($arg->type === 'default') {
                    $aggregate = '-';
                } else {
                    $aggregate = $arg->type;
                }
                $argAggregate[] = $aggregate;
            }

            $argHash = implode(',', $argAggregate);
            if (!isset($callCounts[$name][$argHash])) {
                $callCounts[$name][$argHash] = ['count'=>1, 'callInfo'=>$callInfo, 'aggregate'=>$argAggregate];
            } else {
                ++$callCounts[$name][$argHash]['count'];
            }
        }
    }

    ksort($callCounts);
}

display: {
    foreach ($callCounts as $name=>$callFn) {
        $cli->lightGreen()->bold()->out($call->entry->function);

        $rows = [];
        $hdr = ['<blue>calls</blue>', '<blue>arg1</blue>'];
        $maxCols = 1;

        foreach ($callFn as $idx=>$callCount) {
            list ($name, $traceId, $call, $stack) = $callCount['callInfo'];

            $row = $callCount['aggregate'];
            array_unshift($row, "x{$callCount['count']}");
            $maxCols = max(count($callCount['aggregate']) + 1, $maxCols);
            $rows["{$callCount['count']}|$idx"] = $row;
        }
        krsort($rows, SORT_NUMERIC);

        for ($i = 2; $i < $maxCols; $i++) {
            $hdr[$i] = "<blue>$i</blue>";
        }
        array_unshift($rows, $hdr);

        $cli->columns($rows, $maxCols);
        $cli->br();
    }
}
