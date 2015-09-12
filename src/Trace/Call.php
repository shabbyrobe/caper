<?php
namespace Caper\Trace;

class Call
{
    public $entry;
    public $exit;
    public $return;

    public function __construct(
        Record\Entry $entry,
        Record\Exit_ $exit=null,
        Record\Return_ $return=null)
    {
        $this->entry = $entry;
        $this->exit = $exit;
        $this->return = $return;
    }
}
