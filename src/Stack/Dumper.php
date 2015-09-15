<?php
namespace Caper\Stack;

class Dumper
{
    function __construct(\League\CLImate\CLImate $cli)
    {
        $this->cli = $cli;
    }

    function out(Collector $collector, $showClassNames=false)
    {
        $cli = $this->cli;

        foreach ($collector->callCounts($showClassNames) as $name=>$callFn) {
            $firstCall = current($callFn)['callInfo'];
            $firstEntry[Collector::CALL_INFO]->entry;

            $cli->lightGreen()->bold()->out($firstEntry->function);

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
}

