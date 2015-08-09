<?php
namespace Caper\Trace;

use Caper\Trace\Record;
use Caper\Trace\Record\Entry;
use Caper\Trace\Record\Exit_;
use Caper\Trace\Record\Return_;

class Parser
{
    const XT_IN_NEW = 1;
    const XT_IN_HDR = 2;
    const XT_IN_VER = 3;
    const XT_IN_V4  = 4;
    const XT_IN_V4_FOOTER = 5;

    function parseArgs($args)
    {
        $ret = [];
        foreach ($args as $arg) {
            $ret[] = $this->parseArg($arg);
        }
        return $ret;
    }

    function parseArg($arg)
    {
        $name = null;
        $type = null;
        $value = null;
        $kind = null;
        $contents = null;

        if ($arg[0] == '$') {
            list ($name, $arg) = preg_split('/\s*=\s*/', $arg, 2);
        }

        $first = $arg[0];

        if ($arg === 'TRUE' || $arg === 'FALSE' || $arg === 'true' || $arg === 'false') {
            $type = 'bool';
            $value = $arg === 'TRUE' || $arg === 'true';
        }

        elseif ($arg === 'NULL' || $arg === 'null') {
            $type = 'null';
        }

        elseif ($arg[0] == "'") {
            $type = 'string';
            $value = substr($arg, 1, -1);
        }

        elseif (is_numeric($arg)) {
            $type = strpos($arg, '.') === false ? 'int' : 'double';
            $value = $type === 'int' ? (int)$arg : (double)$arg;
        }

        elseif ($first === 'c' && substr($arg, 0, 6) === 'class ') {
            $type = 'object';
            list (, $kind, $contents) = explode(' ', $arg, 3);
        }

        elseif ($first === 'a' && substr($arg, 0, 6) === 'array ') {
            $type = 'array';
            list (, $contents) = explode(' ', $arg, 3);
        }

        elseif ($first === 'r' && substr($arg, 0, 8) === 'resource') {
            $type = 'resource';
            $kind = $arg;
        }

        elseif ($arg === '???') {
            $type = 'default';
        }

        else {
            throw new \UnexpectedValueException("Unparseable arg '$arg'");
        }

        return (object) compact('name', 'type', 'value', 'kind');
    }

    function recordIterator($traceFile, $query=[])
    {
        $defaults = [
            'limit'      => null,
            'functions'  => null,
            'entryTypes' => null,
        ];
        $query = array_merge($defaults, $query);

        $limit = $query['limit'];

        if (!$query['entryTypes']) {
            $query['entryTypes'] = [Entry::class, Exit_::class, Return_::class];
        }

        foreach ((array)$query['entryTypes'] as $t) { $entryTypeIndex[$t] = true; }

        if (!is_resource($traceFile)) {
            $h = fopen($traceFile, 'r');
            if (!$h) {
                throw new \UnexpectedValueException();
            }
        } else {
            $h = $traceFile;
        }

        $state = self::XT_IN_NEW;

        $traceIndex = 0;
        $trace = null;
        $lineNum = 0;
        $record = 0;

        while (!feof($h)) {
            $lineNum++;
            $line = rtrim(fgets($h));

        parse_line:
            state_new: if ($state === self::XT_IN_NEW) {
                $trace = (object)['id' => $traceIndex++, 'meta' => []];
                $state = self::XT_IN_HDR;
            }

            state_hdr: if ($state === self::XT_IN_HDR) {
                if (strpos($line, 'TRACE START') === 0) {
                    $state = self::XT_IN_VER;
                    goto next_record;
                }
                else {
                    $parts = preg_split('/:\s*/', $line, 2);
                    if (!isset($parts[1])) {
                        throw new \UnexpectedValueException("Invalid header at line $lineNum");
                    }
                    $trace->meta[$parts[0]] = $parts[1];
                }
            }

            state_ver: if ($state === self::XT_IN_VER) {
                if (!isset($trace->meta['File format']) || $trace->meta['File format'] != 4) {
                    throw new \UnexpectedValueException(
                        "Only supports file format 4, found ".(isset($trace->meta['File format']) ? $trace->meta['File format'] : '(null)')
                    );
                }
                $state = self::XT_IN_V4;
            }

            state_trace_v4: if ($state === self::XT_IN_V4) {
                $parts = explode("\t", $line);
                if ($limit && $record >= $limit) {
                    goto done;
                }

                switch ($parts[Record::F_TYPE]) {
                case Entry::TYPE:
                    if (isset($entryTypeIndex[Entry::class])) {
                        $yield = true;
                        if (isset($query['functions'])) {
                            $yield = Entry::allowFormat1($parts, $query);
                        }
                        if ($yield) {
                            yield [$trace, Entry::fromFormat1($parts)];
                            ++$record;
                        }
                    }
                break;

                case Exit_::TYPE:
                    if (isset($entryTypeIndex[Exit_::class])) {
                        yield [$trace, Exit_::fromFormat1($parts)];
                        ++$record;
                    }
                break;

                case Return_::TYPE:
                    if (isset($entryTypeIndex[Return_::class])) {
                        yield [$trace, Return_::fromFormat1($parts)];
                        ++$record;
                    }
                break;
                
                case '':
                    // strange footer line with no type, contains values at col 4 and 5
                    if (count($parts) === 5) {
                        $state = self::XT_IN_V4_FOOTER;
                        goto next_record;
                    }
                    elseif (strpos($line, 'TRACE END') === 0) {
                        $state = self::XT_IN_V4_FOOTER;
                        goto parse_line;
                    }

                default:
                    throw new \UnexpectedValueException("Unexpected record type ".$parts[Record::F_TYPE]." at line $lineNum");
                }
            }

            state_trace_v4_footer: if ($state === self::XT_IN_V4_FOOTER) {
                if ($line) {
                    if (strpos($line, 'TRACE END') === 0) {
                        goto next_record;
                    } else {
                        $state = self::XT_IN_NEW;
                        goto parse_line;
                    }
                }
            }

        next_record:
        }

    done:
    }

    function stackIterator($file)
    {
        $stack = [];
        $entryStack = [];
        $lastLevel = 0;
        $lastTraceId = null;

        $recordIter = $this->recordIterator($file, [], !'objects');

        foreach ($recordIter as list($trace, $record)) {
            if ($lastTraceId === null || $lastTraceId != $trace->id) {
                $lastTraceId = $trace->id;
                $stack = [];
                $entryStack = [];
                $lastLevel = 0;
            }

            if ($lastLevel && $lastLevel == $record->level && $record instanceof Entry) {
                $ret = array_pop($stack);
                if (array_pop($entryStack)) {
                    yield [$trace->id, $ret, $entryStack];
                }
            }
            elseif ($record->level < $lastLevel && $record instanceof Exit_) {
                $ret = array_pop($stack);
                if (array_pop($entryStack)) {
                    yield [$trace->id, $ret, $entryStack];
                }
            }

            // if there is nothing in our stack on Exit or Return, this is
            // (hopefully) a trace that was invoked from a function call - the exit
            // for 'xdebug_trace_start' appears first

            if ($record instanceof Entry) {
                $stack[$record->level] = (object)['entry' => $record, 'exit' => null, 'return' => null];
                $entryStack[] = $record;
            }
            elseif ($record instanceof Exit_) {
                if (isset($stack[$record->level])) {
                    $stack[$record->level]->exit = $record;
                }
            }
            elseif ($record instanceof Return_) {
                if (isset($stack[$record->level])) {
                    $stack[$record->level]->return = $record;
                }
            }

            $lastLevel = $record->level;
        }

        if ($stack && current($stack)->exit) {
            yield [$lastTraceId, current($stack), []];
        }
    }
}
