<?php
namespace Caper\Stack;

class Collector
{
    private $parser;
    private $traceFiles;

    function __construct(\Caper\Config $config, \Caper\Trace\Parser $parser)
    {
        $this->parser = $parser;
        $this->config = $config;
    }

    /**
     * Array of 4-tuples containing ['parsedName', 'traceId', 'call', 'stack']
     *   For parsedName, see Caper\Filter::parseFunctionName
     *   For traceId, see Caper\Trace\Info
     *   'call' contains a Caper\Trace\Call
     *   'stack' contains an array of Caper\Trace\Record\Entry_ of the entire stack for 'call'
     *
     * This is a 4-tuple and not an object because this is the slowest part of
     * the code by a street, and using an object slows things down by about 40% when
     * you have xdebug loaded, which you probably do if you're using Caper.
     */
    private $functions = [];

    private $functionIndex = [];

    private $signatures = false;

    function result()
    {
        return (object) [
            'functions' => $this->functions,
            'functionIndex' => $this->functionIndex,
            'signatures' => $this->signatures ?: [],
        ];
    }

    function collect($traceFile)
    {
        foreach ($this->parser->stackIterator($traceFile) as list ($traceId, $call, $stack)) {
            $parsedName = \Caper\Filter::parseFunctionName($call->entry->function);

            if ($this->config->filter->isIncluded(...$parsedName)) {
                $this->functions[$call->entry->function][] = [$parsedName, $traceId, $call, $stack];
                $this->functionIndex[$call->entry->function] = $parsedName;
            }
        }
    }

    function loadSignatures()
    {
        $this->signatures = caper_fetch_signatures($this->config, $this->functionIndex);
    }

    function callCounts($showClassNames=false)
    {
        $i = 0;
        $callCounts = [];
        foreach ($this->functions as $name => $calls) {
            foreach ($calls as $callIdx => $callInfo) {
                list ($parsedName, $traceId, $call, $stack) = $callInfo;
                $argv = $this->parser->parseArgs($call->entry->argv);

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

        return $callCounts;
    }
}

