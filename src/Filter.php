<?php
namespace Caper;

class Filter
{
    private $root;

    function __construct()
    {
        $this->root = (object)[
            'namespace' => '',
            'nodes' => [],
        ];
    }

    /**
     * Accepts a superset of structures emitted by 'Caper\Func::parse()' - also
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
        return $this->isIncluded(...Func::parse($function));
    }

    /**
     * Accepts a superset of structures emitted by 'Caper\Func::parse()' - also
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
