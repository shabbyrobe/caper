<?php
namespace Caper\Trace\Record;

class Entry extends \Caper\Trace\Record
{
    const TYPE = "0";

    const F_FUNCTION = 5;
    const F_USER_DEFINED = 6;
    const F_REQUIRE = 7;
    const F_FILE = 8;
    const F_LINE = 9;
    const F_ARGC = 10;

    public $timeIndex;
    public $memUsage;
    public $function;
    public $isUserDefined;
    public $requreFile;
    public $file;
    public $line;
    public $argc;
    public $argv = [];
    public $ellipsisArg;

    function getType() { return 'entry'; }

    static function allowFormat1($parts, $query)
    {
        $functionsMatched = true;

        if ($query['functions']) {
            $functionsMatched = false;
            $function = $parts[static::F_FUNCTION];
            foreach ($query['functions'] as $funcPattern) {
                if (preg_match($funcPattern, $function)) {
                    $functionsMatched = true;
                    break;
                }
            }
        }

        return $functionsMatched;
    }

    static function fromFormat1($parts)
    {
        if ($parts[static::F_TYPE] !== static::TYPE) {
            throw new \InvalidArgumentException("Invalid type ".$parts[static::F_TYPE]);
        }
        if (!isset($parts[static::F_LINE])) {
            throw new \InvalidArgumentException();
        }
        $c = new static;
        $c->level = $parts[static::F_LEVEL];
        $c->functionNum = $parts[static::F_FUNCTION_NUM];
        $c->timeIndex = $parts[static::F_TIME_INDEX];
        $c->memUsage = $parts[static::F_MEM_USAGE];

        $c->function = $parts[static::F_FUNCTION];
        if ($parts[6] === "1") {
            $c->isUserDefined = true;
        } elseif ($parts[6] === "0") {
            $c->isUserDefined = false;
        } else {
            throw new \InvalidArgumentException();
        }

        $c->requireFile = $parts[7];
        $c->file = $parts[8];
        $c->line = $parts[static::F_LINE];
        
        list ($c->argc, $c->argv, $c->ellipsisArg) = static::readArgs($parts);

        return $c;
    }

    static function readArgs($parts)
    {
        $argc = 0;
        $argv = [];
        $ellipsisArg = null;

        if (isset($parts[static::F_ARGC])) {
            $argc = $parts[static::F_ARGC];

            if ($argc) {
                $hasEllipsis = false;
                $i = 0;
                foreach (array_slice($parts, static::F_ARGC + 1) as $arg) {
                    if ($arg === '...') {
                        $hasEllipsis = true;
                        $ellipsisArg = $i;
                        $argc -= 1;
                    }
                    else {
                        $argv[$i++] = $arg;
                    }
                }
                $argc = $i;
            }
        }
        return [$argc, $argv, $ellipsisArg];
    }
}
