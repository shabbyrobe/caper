<?php
namespace Caper;

final class Trace
{
    public static $ini = [
        'xdebug.trace_format' => 1,
        'xdebug.collect_assignments' => 0,
        'xdebug.collect_includes' => 0,
        'xdebug.collect_return' => 0,
        'xdebug.collect_vars' => 0,

        // unsure about this stuff.
        'xdebug.collect_params' => 4,
        'xdebug.var_display_max_children' => 0,
        'xdebug.var_display_max_depth' => 0,
        'xdebug.var_display_max_data' => 64,

        // these limits are insane. 300 mb per test!
        // 'xdebug.collect_params' => 4,
        // 'xdebug.var_display_max_children' => -1,
        // 'xdebug.var_display_max_depth' => -1,
        // 'xdebug.var_display_max_data' => -1,
    ];

    private static $iniRestore = [];
    private static $traceFile = null;
    private static $running = false;

    static function init()
    {
        if (!extension_loaded('xdebug')) {
            throw new \RuntimeException();
        }
        self::$traceFile = getenv('CAPER_TRACE_FILE');
        if (!self::$traceFile) {
            throw new \Exception("Can't trace without CAPER_TRACE_FILE env");
        }
        if (!is_writable(dirname(self::$traceFile))) {
            throw new \Exception("CAPER_TRACE_FILE ".self::$traceFile." not writable");
        }
        file_put_contents(self::$traceFile, '');
    }

    static function start()
    {
        if (!self::$traceFile) {
            self::init();
        }

        if (ini_get('xdebug.auto_trace')) {
            return;
        }

        if (self::$running) {
            throw new \BadMethodCallException();
        }

        foreach (static::$ini as $k=>$v) {
            self::$iniRestore[$k] = ini_get($k);
            ini_set($k, $v);
        }

        self::$running = true;

        $options = XDEBUG_TRACE_APPEND | XDEBUG_TRACE_COMPUTERIZED | XDEBUG_TRACE_NAKED_FILENAME;
        xdebug_start_trace(self::$traceFile, $options);
    }

    static function stop()
    {
        if (ini_get('xdebug.auto_trace')) {
            return;
        }

        if (!self::$running) {
            throw new \BadMethodCallException();
        }

        xdebug_stop_trace();
        foreach (self::$iniRestore as $k=>$v) {
            ini_set($k, $v);
        }
        self::$iniRestore = [];
        self::$running = false;
    }
}
