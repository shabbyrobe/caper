<?php
$usage = <<<'DOCOPT'
Usage: run [--show-class-names]

DOCOPT;

$options = (new \Docopt\Handler())->handle($usage);

$cwd = getcwd();
$configFile = "$cwd/caper.yml";
if (!file_exists($configFile)) {
    die("Config file not found\n");
}

$showClassNames = $options['--show-class-names'];

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
    $functionIndex = [];
    foreach ($runner->files as $traceFile) {
        foreach ($parser->stackIterator($traceFile) as list ($traceId, $call, $stack)) {
            $parsedName = \Caper\Filter::parseName($call->entry->function);

            if ($config->filter->isIncluded(...$parsedName)) {
                $functions[$call->entry->function][] = [$parsedName, $traceId, $call, $stack];
                $functionIndex[$call->entry->function] = $parsedName;
            }
        }
    }
}

signatures: {
    $signatures = caper_fetch_signatures($config, $functionIndex);
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
                    $aggregate = $showClassNames ? $arg->kind : $arg->type;
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

        $headerColour = 'light_blue';
        $rows = [];
        $hdr = ["<$headerColour>calls</$headerColour>", "<$headerColour>arg1</$headerColour>"];
        $sigRow = [];
        $maxCols = 1;

        if (isset($signatures[$name])) {
            $sig = $signatures[$name];
            $sigRow[] = "<$headerColour>calls</$headerColour>";
            foreach ($sig['argv'] as $name=>$arg) {
                $sigRow[] = "<$headerColour>".caper_function_param_str($name, $arg)."</$headerColour>";
            }
        }

        foreach ($callFn as $idx=>$callCount) {
            list ($name, $traceId, $call, $stack) = $callCount['callInfo'];

            $row = $callCount['aggregate'];
            array_unshift($row, "x{$callCount['count']}");
            $maxCols = max(count($callCount['aggregate']) + 1, $maxCols);
            $rows["{$callCount['count']}|$idx"] = $row;
        }
        krsort($rows, SORT_NUMERIC);

        for ($i = 2; $i < $maxCols; $i++) {
            $hdr[$i] = "<$headerColour>$i</$headerColour>";
        }
        array_unshift($rows, $sigRow ?: $hdr);

        $cli->columns($rows, $maxCols);
        $cli->br();
    }
}

function caper_function_param_str($name, $arg)
{
    $param = "";
    if ($arg['isVariadic']) {
        $param = "...$$name";
    }
    else {
        $hint = null;
        if     ($arg['class'])      { $hint = $arg['class']; }
        elseif ($arg['isArray'])    { $hint = 'array'; }
        elseif ($arg['isCallable']) { $hint = 'callable'; }

        if ($hint) {
            $param = "$hint ";
        }
        $param .= "$$name";

        if ($arg['hasDefault']) {
            $default = '';
            if ($arg['defaultValueConstant']) {
                $default = $arg['defaultValueConstant'];
            } else {
                $default = json_encode($arg['defaultValue']);
            }
            $param .= '='.$default;
        }
    }

    return $param;
}

function caper_fetch_signatures(\Caper\Config $config, $functions)
{
    $scriptConfig = [
        'bootstrap' => $config->bootstrap,
        'functions' => $functions,
    ];
    $script = strtr(signature_script(), [
        '{{config}}' => base64_encode(serialize($scriptConfig)),
    ]);

    $p = proc_open('php', [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $config->cwd);
    if (!$p) {
        throw new \UnexpectedValueException();
    }
    fwrite($pipes[0], $script);
    fclose($pipes[0]);

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    $ret = proc_close($p);

    if ($ret !== 0) {
        throw new \RuntimeException();
    }

    $data = @unserialize($out);
    if ($data === false) {
        throw new \RuntimeException();
    }

    return $data;
}

function signature_script()
{
$script = <<<'PHP'
$config = @unserialize(@base64_decode("{{config}}"));
if (!is_array($config)) {
    die("Invalid config");
}
foreach ($config['bootstrap'] as $script) {
    require $script;
}

$out = ['classes'=>[], 'functions'=>[]];
foreach ($config['functions'] as $key=>list($kind, $ns, $name)) {
    if ($kind === 'method' || $kind === 'static') {
        $rc = new ReflectionClass(implode('\\', $ns));    
        $rm = $rc->getMethod($name);
        $out[$key] = rf_dump($rm);
    }
    elseif ($kind === 'function') {
        $rf = new ReflectionFunction(implode('\\', $ns).$name);
        $out[$key] = rf_dump($rf);
    }
}

echo serialize($out);

function rf_dump($rm)
{
    $argv = [];
    foreach ($rm->getParameters() as $p) {
        $argv[$p->name] = [
            'allowsNull' => $p->allowsNull(),
            'canBePassedByValue' => $p->canBePassedByValue(),
            'defaultValue' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null,
            'defaultValueConstant' => $p->isDefaultValueAvailable() && $p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : null,
            'pos' => $p->getPosition(),
            'isArray' => $p->isArray(),
            'isCallable' => $p->isCallable(),
            'hasDefault' => $p->isDefaultValueAvailable(),
            'hasDefaultConstant' => $p->isDefaultValueAvailable() ? $p->isDefaultValueConstant() : false,
            'isOptional' => $p->isOptional(),
            'isVariadic' => $p->isVariadic(),
            'class' => $p->getClass() ? $p->getClass()->name : null,
        ];
    }
    return [
        'name' => $rm->name,
        'file' => $rm->getFileName(),
        'line' => $rm->getStartLine(),
        'variadic' => $rm->isVariadic(),
        'byRef' => $rm->returnsReference(),
        'argc' => $rm->getNumberOfParameters(),
        'argcRequired' => $rm->getNumberOfRequiredParameters(),
        'argv' => $argv,
    ];
}
PHP;
return '<'.'?php '.$script;
}
