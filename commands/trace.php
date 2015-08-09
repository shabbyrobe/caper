<?php
$usage = <<<'DOCOPT'
Usage: trace records [--type=<type>...] [--limit=<limit>] <file>
       trace pop <file>

Options:
  --type=<type>  Record type (entry, exit, return). [default: all]
  --limit=<num>  Limit to n records

DOCOPT;

$options = (new \Docopt\Handler())->handle($usage);

$parser = new \Caper\Trace\Parser;

if ($options['records']) {
    $file = fopen_arg($options['<file>'], 'r');
    $query = [];
    if ($options['--limit']) {
        $query['limit'] = $options['--limit'];
    }
    foreach ($options['--type'] as $type) {
        switch ($type) {
        case 'entry' : $query['entryTypes'][] = \Caper\Trace\Record\Entry::class;   break;
        case 'exit'  : $query['entryTypes'][] = \Caper\Trace\Record\Exit_::class;   break;
        case 'return': $query['entryTypes'][] = \Caper\Trace\Record\Return_::class; break;
        case 'all'   : $query['entryTypes']   = []; break;
        }
    }
    foreach ($parser->recordIterator($file, $query) as list($trace, $record)) {
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
