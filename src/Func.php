<?php
namespace Caper;

final class Func
{
    const FUNC_KIND  = 0;
    const FUNC_QNAME = 1;
    const FUNC_FNAME = 2;

    private function __construct() {}

    public static $kinds = [
        'namespace', 'class', 'static', 'method', 'function', 'main', 'closure',
    ];

    // If it's a builtin and `new ReflectionFunction(...)` says it doesn't exist,
    // it's probably a language construct not a function.
    public static $statements = [
        'require' => true, 'require_once' => true,
        'include' => true, 'include_once' => true,
        'exit'    => true, 'die'          => true,
        'empty'   => true, 'isset'        => true,
        'eval'    => true, 'unset'        => true,
    ];

    /**
     * Converts a stringly function name as seen in an XDebug trace into a
     * 3-tuple:
     * 
     *     ['kind', ['qualifying', 'prefix'], 'memberName']
     *
     * The qualifying prefix can be either a class name or a
     * namespace name depending on the member. 
     */
    static function parse($name)
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
}
