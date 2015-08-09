<?php
namespace Caper;

class Filter
{
    private $root;

    function __construct()
    {
        $this->root = (object)[
            'nodes' => [],
        ];
    }

    static function parseName($name)
    {
        $kind = null;

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
            return ['function', [], $name];
        }
    }

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

    function isNameIncluded($name)
    {
        return $this->isIncluded(...self::parseName($name));
    }

    function isIncluded($kind, $path, $func=null)
    {
        $cur = $this->root;
        $status = $cur->namespace;

        foreach ($path as $name) {
            if (!isset($cur->nodes[$name])) {
                return $status;
            }
            $cur = $cur->nodes[$name];
            if (isset($cur->namespace)) {
                $status = $cur->namespace;
            }
        }

        if ($kind === 'namespace') {
            return $status;
        }
        elseif ($kind === 'class') {
            return !isset($cur->class) ? $status : $cur->class;
        }
        elseif ($kind === 'function' || $kind === 'static' || $kind === 'method') {
            if (!$func) {
                throw new \InvalidArgumentException();
            }
            if (($kind === 'static' || $kind === 'method') && isset($cur->class)) {
                $status = $cur->class;
            }
            return isset($cur->{$kind}[$func]) ? $cur->{$kind}[$func] : $status;
        }
        elseif ($kind === 'closure' || $kind === 'main') {
            return false;
        }
        else {
            throw new \InvalidArgumentException("Unknown kind $kind");
        }
    }
}
