<?php
$usage = <<<'DOCOPT'
Run a PHP script using Caper's trace options

Usage: php [options] run <script> [<args>...]

Options:
  -o, --output=<file>

DOCOPT;

$options = (new \Docopt\Handler(['optionsFirst'=>true]))->handle($usage);

$cwd = getcwd();
$output = $options['--output'] ?: $cwd.'/'.uniqid('caper-', true).'.xt';

if (!($config = caper_config_load($cwd, $_SERVER['HOME'], !!'default'))) {
    die("No config found\n");
}

if (!$_ENV && strpos(ini_get('variables_order'), 'E') === false) {
    throw new \Exception('Please ensure "E" appears in your variables_order ini setting');
}

$runner = new \Caper\Trace\Runner($output);
$script = [
    'script' => $options['<script>'],
    'args'   => $options['<args>'],
];
$runner->scriptPHP($config, $output, $script, $_ENV);

