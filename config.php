<?php
const CAPER_BASE_PATH = __DIR__;
require CAPER_BASE_PATH.'/vendor/autoload.php';
throw_on_error();

function fopen_arg($f, $mode, array $opts=[])
{
    $opts = array_merge(['useInclude'=>null, 'context'=>null, 'throw'=>true], $opts);

    $openName = null;
    if ($f === '-') {
        if (strpos($mode, '+') !== false) {
            throw new \InvalidArgumentException();
        }
        if ($mode[0] == 'r') {
            $openName = 'php://stdin';
        } elseif ($mode[0] == 'w' || $mode[0] == 'x' || $mode[0] == 'c') {
            $openName = 'php://stdout';
        } else {
            throw new \InvalidArgumentException();
        }
    }
    elseif (preg_match('~^/dev/fd/(\d+)$~', $f, $match)) {
        $openName = 'php://fd/'.$match[1];
    }
    else { 
        $openName = $f;
    }
    
    // PHP functions are *really* fussy about default parameters
    if ($opts['context']) {
        $h = fopen($openName, $mode, $opts['useInclude'] ?: false, $opts['context']);
    } elseif ($opts['useInclude'] !== null) {
        $h = fopen($openName, $mode, $opts['useInclude']);
    } else {
        $h = fopen($openName, $mode);
    }

    if (!$h && $opts['throw']) {
        throw new \RuntimeException("Could not open $openName");
    }

    return $h;
}

function throw_on_error()
{
    static $set=false;
    if (!$set) {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $reporting = error_reporting();
            if ($reporting > 0 && ($reporting & $errno)) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        });
        $set = true;
    }
}

function caper_config_load($wd, $home=null, $default=false)
{
    $found = false;
    $config = null;

    $files = [
        "$wd/caper.yml",
        "$wd/caper.json",
    ];
    if ($home) {
        $files[] = "$home/.caper.yml";
        $files[] = "$home/.caper.json";
    }
    foreach ($files as $file) {
        if (file_exists($file)) {
            $found = true;        
            break;
        }
    }
    if ($found) {
        $config = \Caper\Config::fromFile($file, $wd);
    } elseif ($default) {
        $config = new \Caper\Config($wd);
    }
    return $config;
}
