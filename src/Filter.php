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
        $method = null;
        $exp = explode('->', $name);
        if (isset($exp[1])) {
            $method = $exp[1];
        }

        $ns = explode('\\', $exp[0]);
        $class = array_pop($ns) ?: null;

        return [$ns, $class, $method];
    }

    public function add($status, $ns=null, $class=null, $method=null)
    {
        $status = !!$status;

        $cur = $this->root;
        if (!$ns) {
            $cur->ns = $status;
            return;
        }

        foreach ($ns as $name) {
            if (!isset($cur->nodes[$name])) {
                $cur->nodes[$name] = (object)['name' => $name, 'nodes' => []];
            }
            $cur = $cur->nodes[$name];
        }

        if ($class) {
            if (!isset($cur->nodes[$class])) {
                $cur->nodes[$class] = (object)['name' => $class, 'nodes' => []];
            }
            $cur = $cur->nodes[$class];

            if ($method) {
                $cur->methods[$method] = $status;
            }
            else {
                $cur->class = $status;
                if ($status == false) {
                    $cur->methods = [];
                }
            }
        }
        else {
            $cur->ns = $status;
            if ($status == false) {
                $cur->nodes = [];
                $cur->methods = false;
            }
        }
    }

    function isNameIncluded($name)
    {
        return $this->isIncluded(...self::parseName($name));
    }

    function isIncluded($ns, $class=null, $method=null)
    {
        $cur = $this->root;
        $status = $cur->ns;

        foreach ($ns as $name) {
            if (!isset($cur->nodes[$name])) {
                return $status;
            }
            $cur = $cur->nodes[$name];
            if (isset($cur->ns)) {
                $status = $cur->ns;
            }
        }

        if ($class) {
            if (!isset($cur->nodes[$class])) {
                return $status;
            }
            $cur = $cur->nodes[$class];
            if (isset($cur->class)) {
                $status = $cur->class;
            }

            if ($method) {
                return isset($cur->methods[$method]) 
                    ? $cur->methods[$method]
                    : $status;
            }
        }

        return $status;
    }
}
