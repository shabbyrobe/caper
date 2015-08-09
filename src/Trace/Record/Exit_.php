<?php
namespace Caper\Trace\Record;

class Exit_ extends \Caper\Trace\Record
{
    const TYPE = "1";

    public $timeIndex;
    public $memUsage;

    function getType() { return 'exit'; }

    static function fromFormat1($parts)
    {
        if ($parts[static::F_TYPE] !== static::TYPE) {
            throw new \InvalidArgumentException("Invalid type ".$parts[static::F_TYPE]);
        }
        if (!isset($parts[static::F_MEM_USAGE])) {
            throw new \InvalidArgumentException();
        }
        $c = new static;
        $c->level = $parts[static::F_LEVEL];
        $c->functionNum = $parts[static::F_FUNCTION_NUM];
        $c->timeIndex = $parts[static::F_TIME_INDEX];
        $c->memUsage = $parts[static::F_MEM_USAGE];
        return $c;
    }
}
