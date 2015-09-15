<?php
namespace Caper\Test;

use Caper\Func;

class FuncTest extends \PHPUnit_Framework_TestCase
{
    function parseFunctionNameProvider()
    {
        return [
             ['\Class\Name->methodName'    , ['method', ['Class', 'Name'], 'methodName']],
             ['Class\Name->methodName'     , ['method', ['Class', 'Name'], 'methodName']],
             ['\Class\Name::methodName'    , ['static', ['Class', 'Name'], 'methodName']],
             ['Class\Name::methodName'     , ['static', ['Class', 'Name'], 'methodName']],
             ['{main}'                     , ['main', [], null]],
             ['{closure}'                  , ['closure', [], '{closure}']],
             ['\Namespaced\functionName'   , ['function', ['Namespaced'], 'functionName']],
             ['Namespaced\functionName'    , ['function', ['Namespaced'], 'functionName']],
             ['functionName'               , ['function', [], 'functionName']],
        ];
    }

    /** @dataProvider parseFunctionNameProvider */
    function testParseFunctionName($in, $out)
    {
        return $this->assertEquals($out, \Caper\Func::parse($in));
    }
}
