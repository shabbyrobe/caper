<?php
namespace Caper\Trace;

class Runner
{
    public static $handledScripts = ['shell', 'php'];

    public $runId;
    public $files = [];

    public function __construct($outputPath=null)
    {
        $this->runId = uniqid('caper-', true);

        if (!$outputPath) {
            $outputPath = sys_get_temp_dir();
            $shutdown = new \Caper\Helper\ShutdownCleanup();
            $shutdown->register(function() {
                foreach ($this->files as $idx=>$file) {
                    @unlink($file);
                    unset($this->files[$idx]);
                }
            });
        }
        $this->outputPath = $outputPath;
    }

    public function run($config)
    {
        foreach ($config->scripts as $script) {
            if (!in_array($script['type'], self::$handledScripts)) {
                throw new \Exception("Unknown script type {$script['type']}");
            }
        }

        foreach ($config->scripts as $idx=>$script) {
            $file = $this->files[] = "{$this->outputPath}/{$this->runId}-$idx.xt";
            $type = $script['type'];
            $this->{"script$type"}($config, $file, $script);
        }
    }

    public function scriptShell($config, $file, $script, $env=[])
    {
        if (!preg_match('/\.xt$/', $file)) {
            throw new \Exception("Script name has to end with .xt");
        }

        $args = &$script['args'];
        $cmd  = &$script['cmd'];
        if (!$cmd) {
            throw new \RuntimeException();
        }

        $env = [
            'CAPER_RUN' => true,
            'CAPER_TRACE_FILE' => $file,
        ];

        foreach ($args ?: [] as $arg) {
            $cmd .= ' '.escapeshellarg($arg);
        }

        $p = proc_open($cmd, [STDIN, STDOUT, STDERR], $pipes, $config->cwd, $env);
        $ret = proc_close($p);
        if ($ret !== 0) {
            die("Caper failed: script '{$cmd}' had non-zero exit status: $ret\n");
        }

        if (!file_exists($file)) {
            die("Caper failed: script '{$cmd}' did not produce a trace file\n");
        }
    }

    public function scriptPHP($config, $file, $script)
    {
        if (!preg_match('/\.xt$/', $file)) {
            // XDEBUG_TRACE_NAKED_FILENAME is a start, but it doesn't cover
            // ini files. I hope some day the automatic .xt extension will be
            // discarded fully for the terrible misfeature that it is.
            throw new \Exception("Script name has to end with .xt");
        }

        $args = &$script['args'];
        $name = &$script['script'];
        if (!$name) {
            throw new \RuntimeException();
        }

        $cmd = "php ";
        if (!isset($script['trace']) || $script['trace']) {
            $info = pathinfo($file);
            $ini = \Caper\Tracer::$ini;
            $ini['xdebug.auto_trace'] = 1;
            $ini['xdebug.trace_output_name'] = $info['filename'];
            $ini['xdebug.trace_output_dir']  = $info['dirname'];

            foreach ($ini as $k=>$v) {
                $cmd .= '-d '.escapeshellarg("$k=$v").' ';
            }
        }
        $cmd .= $name;
        $script['cmd'] = $cmd;

        return $this->scriptShell($config, $file, $script);
    }
}
