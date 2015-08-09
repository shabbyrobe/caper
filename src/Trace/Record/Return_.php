<?php
namespace Caper\Trace\Record;

class Return_ extends \Caper\Trace\Record
{
    const TYPE = 'R';

    public $returnValue;

    function getType() { return 'return'; }

    static function fromFormat1(array $parts)
    {
        if ($parts[static::F_TYPE] !== static::TYPE) {
            throw new \InvalidArgumentException("Invalid type ".$parts[static::F_TYPE]);
        }
        if (!isset($parts[5])) {
            throw new \InvalidArgumentException();
        }
        $c = new static;
        $c->level = $parts[static::F_LEVEL];
        $c->functionNum = $parts[static::F_FUNCTION_NUM];
        $c->returnValue = $parts[5];
        return $c;
    }
}
