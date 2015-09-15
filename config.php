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

function caper_function_param_str($name, $arg)
{
    $param = "";
    if ($arg['isVariadic']) {
        $param = "...$$name";
    }
    else {
        $hint = null;
        if     ($arg['class'])      { $hint = $arg['class']; }
        elseif ($arg['isArray'])    { $hint = 'array'; }
        elseif ($arg['isCallable']) { $hint = 'callable'; }

        if ($hint) {
            $param = "$hint ";
        }
        $param .= "$$name";

        if ($arg['hasDefault']) {
            $default = '';
            if ($arg['defaultValueConstant']) {
                $default = $arg['defaultValueConstant'];
            } else {
                $default = json_encode($arg['defaultValue']);
            }
            $param .= '='.$default;
        }
    }

    return $param;
}

function caper_opt_handle($usage, $options=[])
{
    $options['exit'] = false;
    $out = (new \Docopt\Handler($options))->handle($usage);
    
    if ($out->status != 0) {
        echo rtrim($out->output, "\n")."\n";
        exit($out->status);;
    }
    return $out;
}

function caper_header(\League\CLImate\CLIMate $cli)
{
    $header = 
        "<light_blue>".
        "┌───────────────────────┐\n".
        "│  __                   │\n".
        "│ / ()  _,       _  ,_  │\n".
        "│|     / |  |/\_|/ /  | │\n".
        "│ \___/\/|_/|_/ |_/   |/│\n".
        "│          (|           │\n".
        "└───────────────────────┘".
        "</light_blue>\n"
    ;
    $cli->out($header);
}

/**
 * Opens a subprocess, loads the bootstrap files in your Caper\Config and 
 * retrieves signature details for the functions passed.
 *
 * @param $functions array 3-tuples containing 'kind', 'qualifying prefix' 
 *     and 'function name'. See Caper\Filter for more information
 *
 * @return array Function signatures digested from ReflectionFunctionAbstract.
 *     Each item in $functions will have a corresponding entry in this returned
 *     array with the same key.
 */
function caper_fetch_signatures(\Caper\Config $config, array $functions)
{
    $scriptConfig = [
        'bootstrap'  => $config->bootstrap,
        'functions'  => $functions,
    ];
    $script = strtr(caper_signature_script(), [
        '{{config}}' => base64_encode(serialize($scriptConfig)),
    ]);

    $p = proc_open('php', [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $config->cwd);
    if (!$p) {
        throw new \UnexpectedValueException();
    }
    fwrite($pipes[0], $script);
    fclose($pipes[0]);

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    $ret = proc_close($p);

    if ($ret !== 0) {
        throw new \RuntimeException($err);
    }

    $data = @unserialize($out);
    if ($data === false) {
        throw new \RuntimeException();
    }

    return $data;
}

function caper_signature_script()
{
$script = <<<'PHP'
$config = @unserialize(@base64_decode("{{config}}"));
if (!is_array($config)) {
    die("Invalid config");
}
foreach ($config['bootstrap'] as $script) {
    require $script;
}

$out = ['classes'=>[], 'functions'=>[]];
foreach ($config['functions'] as $key=>list($kind, $ns, $name)) {
    if ($kind === 'method' || $kind === 'static') {
        $rc = new ReflectionClass(implode('\\', $ns));    
        $rm = $rc->getMethod($name);
        $out[$key] = rf_dump($rm);
    }
    elseif ($kind === 'function') {
        $rf = new ReflectionFunction(implode('\\', $ns).$name);
        $out[$key] = rf_dump($rf);
    }
}

echo serialize($out);

function rf_dump($rm)
{
    $argv = [];
    foreach ($rm->getParameters() as $p) {
        $argv[$p->name] = [
            'allowsNull' => $p->allowsNull(),
            'canBePassedByValue' => $p->canBePassedByValue(),
            'defaultValue' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null,
            'defaultValueConstant' => $p->isDefaultValueAvailable() && $p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : null,
            'pos' => $p->getPosition(),
            'isArray' => $p->isArray(),
            'isCallable' => $p->isCallable(),
            'hasDefault' => $p->isDefaultValueAvailable(),
            'hasDefaultConstant' => $p->isDefaultValueAvailable() ? $p->isDefaultValueConstant() : false,
            'isOptional' => $p->isOptional(),
            'isVariadic' => $p->isVariadic(),
            'class' => $p->getClass() ? $p->getClass()->name : null,
        ];
    }
    return [
        'name' => $rm->name,
        'file' => $rm->getFileName(),
        'line' => $rm->getStartLine(),
        'variadic' => $rm->isVariadic(),
        'byRef' => $rm->returnsReference(),
        'argc' => $rm->getNumberOfParameters(),
        'argcRequired' => $rm->getNumberOfRequiredParameters(),
        'argv' => $argv,
    ];
}
PHP;
return '<'.'?php '.$script;
}
