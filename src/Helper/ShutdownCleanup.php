<?php
namespace Caper\Helper;

class ShutdownCleanup
{
    private $shutdownClosure;
    private $cleaners;
    private $registered;

    public static $instances = [];

    static function cleanupAll()
    {
        foreach (self::$instances as $i) {
            $i->cleanup();
        }
    }

    function __construct()
    {
        $this->shutdownClosure = function() {
            $this->cleanup();
        };
        $this->cleaners = new \SplObjectStorage;
    
        self::$instances[] = $this;
    }

    function register($cleaner)
    {
        if (!$this->registered) {
            register_shutdown_function($this->shutdownClosure);
            $this->registered = true;
        }
        $this->cleaners->attach($cleaner);
    }

    function unregister($cleaner)
    {
        if ($this->cleaners->contains($cleaner)) {
            $this->cleaners->detach($cleaner);
        }   
    }

    private function cleanup()
    {   
        $rem = []; 
        foreach ($this->cleaners as $p) {
            call_user_func($p);
            $rem[] = $p;
        }

        foreach ($rem as $p) {
            $this->cleaners->detach($p);
        }
    }
}
