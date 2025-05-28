<?php

namespace dokuwiki\plugin\isolator\test;

use DokuWikiTest;
use dokuwiki\plugin\isolator\IdUtils;

/**
 * Tests for the isolator plugin
 *
 * @group plugin_isolator
 * @group plugins
 */
class IdUtilsTest extends DokuWikiTest
{
    /**
     * Data provider for testing isInNamespace method
     *
     * @return array Test cases
     */
    public function isInNamespaceProvider()
    {
        return [
            'root namespace accepts any id' => ['page', '', true],
            'root namespace accepts namespaced id' => ['namespace:page', '', true],
            'exact namespace match' => ['namespace:page', 'namespace', true],
            'nested namespace match' => ['namespace:subns:page', 'namespace', true],
            'deeper nested namespace match' => ['namespace:subns:subsubns:page', 'namespace', true],
            'no match with different namespace' => ['other:page', 'namespace', false],
            'no match with partial namespace' => ['name:page', 'namespace', false],
            'no match with page only' => ['page', 'namespace', false],
            'empty id' => ['', 'namespace', false],
            'empty id in root namespace' => ['', '', true],
        ];
    }

    /**
     * @dataProvider isInNamespaceProvider
     */
    public function testIsInNamespace($id, $namespace, $expected)
    {
        $result = IdUtils::isInNamespace($id, $namespace);
        $this->assertSame($expected, $result);
    }
}
