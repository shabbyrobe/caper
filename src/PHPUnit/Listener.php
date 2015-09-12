<?php
namespace Caper\PHPUnit;

class Listener extends \PHPUnit_Framework_BaseTestListener
{
    public function startTest(\PHPUnit_Framework_Test $test)
    {
        \Caper\Tracer::start();
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        \Caper\Tracer::stop();
    }
}
