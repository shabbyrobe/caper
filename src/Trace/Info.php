<?php
namespace Caper\Trace;

class Info
{
    public $id;
    public $meta = [];

    public function __construct($id)
    {
        $this->id = $id;
    }
}
