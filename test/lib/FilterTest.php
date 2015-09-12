<?php
namespace Caper\Test;

use Caper\Filter;

class FilterTest extends \PHPUnit_Framework_TestCase
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
        return $this->assertEquals($out, \Caper\Filter::parseFunctionName($in));
    }

    function testFilterFunction()
    {
        $filter = new Filter();
        $filter->add(true, 'function', [], 'pants');

        $this->assertTrue($filter->isFunctionIncluded('pants'));
        $this->assertFalse($filter->isFunctionIncluded('notPants'));
        $this->assertFalse($filter->isFunctionIncluded('pantsNot'));
        $this->assertFalse($filter->isIncluded('namespace', []));
    }

    function testFilterWhitelistAll()
    {
        $filter = new Filter();
        $filter->add(true, 'namespace', []);

        $this->assertTrue($filter->isFunctionIncluded('pants'));
        $this->assertTrue($filter->isIncluded('class', ['Foo']));
        $this->assertTrue($filter->isIncluded('namespace', ['Yep']));
        $this->assertTrue($filter->isIncluded('namespace', []));
    }

    function testFilterWhitelistAllBlacklistFunction()
    {
        $filter = new Filter();
        $filter->add(true, 'namespace', []);
        $filter->add(false, 'function', [], 'pants');

        $this->assertFalse($filter->isFunctionIncluded('pants'));
        $this->assertTrue ($filter->isIncluded('class', ['Foo']));
        $this->assertTrue ($filter->isIncluded('namespace', ['Yep']));
        $this->assertTrue ($filter->isIncluded('namespace', []));
    }

    function testFilterBlacklistClassWhitelistMethod()
    {
        $filter = new Filter();
        $filter->add(true, 'namespace', []);
        $filter->add(false, 'class', ['Pants']);
        $filter->add(true, 'method', ['Pants'], 'pants');

        $this->assertFalse($filter->isIncluded('class', ['Pants']));
        $this->assertTrue ($filter->isIncluded('method', ['Pants'], 'pants'));
    }

    function testFilterBlacklistClassWhitelistStatic()
    {
        $filter = new Filter();
        $filter->add(true, 'namespace', []);
        $filter->add(false, 'class', ['Pants']);
        $filter->add(true, 'static', ['Pants'], 'pants');

        $this->assertFalse($filter->isIncluded('class', ['Pants']));
        $this->assertFalse($filter->isIncluded('method', ['Pants'], 'pants'));
        $this->assertTrue ($filter->isIncluded('static', ['Pants'], 'pants'));
    }

    function testFilterBlacklistNamespace()
    {
        $filter = new Filter();
        $filter->add(true , 'namespace', []);
        $filter->add(false, 'namespace', ['Pants']);

        $this->assertFalse($filter->isIncluded('namespace', ['Pants']));
        $this->assertFalse($filter->isIncluded('class',     ['Pants', 'Yep']));
        $this->assertFalse($filter->isIncluded('function',  ['Pants'], 'foo'));
        $this->assertFalse($filter->isIncluded('function',  ['Pants', 'Yep'], 'foo'));

        $this->assertTrue ($filter->isIncluded('function',  [], 'Pants'));

        // Excluding the namespace 'Pants' should not exclude the class 'Pants'
        $this->assertTrue ($filter->isIncluded('class',     ['Pants']));
    }

    function testFilterBlacklistAllWhitelistClass()
    {
        $filter = new Filter();
        $filter->add(false, 'namespace', []);
        $filter->add(true , 'class', ['Pants']);

        $this->assertFalse($filter->isIncluded('namespace', ['Pants']));
        $this->assertFalse($filter->isIncluded('namespace', []));
        $this->assertTrue ($filter->isIncluded('class', ['Pants']));
    }

    function testFilterWhitelistMethodDoesntWhitelistStatic()
    {
        $filter = new Filter();
        $filter->add(true, 'method', ['Pants', 'Trou'], 'yep');

        $this->assertFalse($filter->isIncluded('static', ['Pants', 'Trou'], 'yep'));
        $this->assertTrue ($filter->isIncluded('method', ['Pants', 'Trou'], 'yep'));
    }

    function testNamespaceNesting()
    {
        $filter = new Filter();
        $filter->add(true , 'namespace', ['Foo']);
        $filter->add(false, 'namespace', ['Foo', 'Bar']);
        $filter->add(true , 'namespace', ['Foo', 'Bar', 'Baz']);

        $this->assertTrue ($filter->isIncluded('namespace', ['Foo']));
        $this->assertTrue ($filter->isIncluded('namespace', ['Foo', 'Qux']));
        $this->assertFalse($filter->isIncluded('namespace', ['Foo', 'Bar']));
        $this->assertFalse($filter->isIncluded('namespace', ['Foo', 'Bar', 'Qux']));
        $this->assertTrue ($filter->isIncluded('namespace', ['Foo', 'Bar', 'Baz']));
        $this->assertTrue ($filter->isIncluded('namespace', ['Foo', 'Bar', 'Baz', 'Qux']));
    }


}
