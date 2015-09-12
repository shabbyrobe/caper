<?php
namespace Caper;

class Filter
{
    private $root;

    public static $kinds = [
        'namespace', 'class', 'static', 'method', 'function', 'main', 'closure',
    ];

    public static $statements = [
        'require' => true, 'require_once' => true,
        'include' => true, 'include_once' => true,
        'exit'    => true, 'die'          => true,
        'empty'   => true, 'isset'        => true,
        'eval'    => true, 'unset'        => true,
    ];

    function __construct()
    {
        $this->root = (object)[
            'namespace' => '',
            'nodes' => [],
        ];
    }

    /**
     * Converts a stringly function name as seen in an XDebug trace into a
     * 3-tuple:
     * 
     *     ['kind', ['qualifying', 'prefix'], 'memberName']
     *
     * The qualifying prefix can be either a class name or a
     * namespace name depending on the member. 
     */
    static function parseFunctionName($name)
    {
        $kind = null;

        $name = ltrim($name, '\\');
        $exp = explode('->', $name, 2);
        if (isset($exp[1])) {
            $ns = explode('\\', $exp[0]);
            return ['method', $ns, $exp[1]];
        }

        $exp = explode('::', $name, 2);
        if (isset($exp[1])) {
            $ns = explode('\\', $exp[0]);
            return ['static', $ns, $exp[1]];
        }

        if ($name === '{main}') {
            return ['main', [], null];
        }
        elseif (strpos($name, '{closure') === 0) {
            return ['closure', [], $name];
        }

        $sep = strrpos($name, '\\');
        if ($sep > 0) {
            $ns = explode('\\', substr($name, 0, $sep));
            return ['function', $ns, substr($name, $sep+1)];
        }
        else {
            if (!isset(static::$statements[$name])) {
                return ['function', [], $name];
            } else {
                return ['statement', [], $name];
            }
        }
    }

    /**
     * Accepts a superset of structures emitted by 'parseFunctionName' - also
     * accepts 'class' or 'namespace' kinds.
     *
     * @param $status bool
     * @param $kind string  See self::$kinds
     * @param $path array|string  Qualifying prefix. If kind is 'class',
     *     'method' or 'static', $path is a class name. If kind is 'function' or
     *     'namespace', $path is a namespace.
     * @param $func string  If kind is 'function', 'method' or 'static',
     *     $func is the function name without a qualifying prefix.
     */
    public function add($status, $kind, $path, $func=null)
    {
        $status = !!$status;

        $cur = $this->root;
        if ($path) {
            if (is_string($path)) {
                $path = explode('\\', trim($path, '\\'));
            }
        }

        foreach ($path ?: [] as $part) {
            if (!isset($cur->nodes[$part])) {
                $cur->nodes[$part] = (object)['name' => $part, 'nodes' => []];
            }
            $cur = $cur->nodes[$part];
        }

        if ($kind === 'static' || $kind === 'method' || $kind === 'function') {
            if (!$func) {
                throw new \InvalidArgumentException();
            }
            $cur->{$kind}[$func] = $status;
        }
        elseif ($kind === 'namespace' || $kind === 'class') {
            if ($func) {
                throw new \InvalidArgumentException();
            }
            $cur->$kind = $status;
            if (!$status) {
                if ($kind === 'namespace') {
                    $cur->nodes = [];
                    unset($cur->nodes);
                    unset($cur->function);
                }
                else {
                    unset($cur->method);
                }
            }
        }
        else {
            throw new \InvalidArgumentException("Unknown kind $kind");
        }
    }

    /**
     * Checks if an xdebug function string is included by the filter
     */
    function isFunctionIncluded($function)
    {
        return $this->isIncluded(...self::parseFunctionName($function));
    }

    /**
     * Accepts a superset of structures emitted by 'parseFunctionName' - also
     * accepts 'class' or 'namespace' kinds.
     *
     * @see self->add() for an explanation of arguments.
     */
    function isIncluded($kind, $path, $func=null)
    {
        $cur = $this->root;

        // for classes, the 'namespace' portion ends one segment before
        // the end. prevStatus lets us keep track of that.
        $status = $prevStatus = $cur->namespace;

        foreach ($path as $name) {
            $prevStatus = $status;
            if (!isset($cur->nodes[$name])) {
                return $status;
            }
            $cur = $cur->nodes[$name];
            if (isset($cur->namespace)) {
                $status = $cur->namespace;
            }
        }

        if ($kind === 'namespace') {
            if ($func) {
                throw new \InvalidArgumentException();
            }
            return $status == true;
        }
        elseif ($kind === 'class') {
            if ($func) {
                throw new \InvalidArgumentException();
            }
            return (!isset($cur->class) ? $prevStatus : $cur->class) == true;
        }
        elseif ($kind === 'function' || $kind === 'static' || $kind === 'method') {
            if (!$func) {
                throw new \InvalidArgumentException();
            }
            if (($kind === 'static' || $kind === 'method') && isset($cur->class)) {
                $status = $cur->class;
            }
            return (isset($cur->{$kind}[$func]) ? $cur->{$kind}[$func] : $status) == true;
        }
        elseif ($kind === 'closure' || $kind === 'main' || $kind === 'statement') {
            // TODO: maybe these should be supported in some way
            return false;
        }
        else {
            throw new \InvalidArgumentException("Unknown kind $kind");
        }
    }
}
