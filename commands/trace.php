<?php
$usage = <<<'DOCOPT'
Usage: trace records <file>
       trace pop <file>

DOCOPT;

$options = (new \Docopt\Handler())->handle($usage);

$parser = new \Caper\Trace\Parser;

if ($options['records']) {
    $file = fopen_arg($options['<file>'], 'r');
    foreach ($parser->recordIterator($file) as list($trace, $record)) {
        $out = (array) $record;
        $out['trace'] = $trace->id;
        $out['type']  = $record->getType();
        echo json_encode($out)."\n";
    }
}

elseif ($options['pop']) {
    $file = fopen_arg($options['<file>'], 'r');
    foreach ($parser->stackIterator($file) as list($traceId, $popped, $stack)) {
        $out = (array) $popped;
        $out['trace'] = $traceId;
        $out['stack'] = $stack;
        echo json_encode($out)."\n";
    }
}
