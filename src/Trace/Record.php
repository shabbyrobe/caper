<?php
namespace Caper\Trace;

abstract class Record
{
    const F_LEVEL        = 0;
    const F_FUNCTION_NUM = 1;
    const F_TYPE         = 2;
    const F_TIME_INDEX   = 3;
    const F_MEM_USAGE    = 4;

    abstract function getType();
}
