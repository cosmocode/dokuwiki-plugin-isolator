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

    /**
     * Test that empty ID throws exception in isInNamespace
     */
    public function testIsInNamespaceEmptyIdThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Empty ID is not allowed');
        IdUtils::isInNamespace('', 'namespace');
    }

    /**
     * Data provider for testing isInSameNamespace method
     *
     * @return array Test cases
     */
    public function isInSameNamespaceProvider()
    {
        return [
            'exact namespace match' => ['namespace:page', 'namespace', true],
            'no match with different namespace' => ['other:page', 'namespace', false],
            'no match with nested namespace' => ['namespace:subns:page', 'namespace', false],
            'no match with parent namespace' => ['ns:page', 'ns:subns', false],
            'no match with child namespace' => ['ns:subns:page', 'ns', false],
            'no match with page only' => ['page', 'namespace', false],
            'root namespace with page only' => ['page', '', true],
            'root namespace with namespaced page' => ['namespace:page', '', false],
        ];
    }

    /**
     * @dataProvider isInSameNamespaceProvider
     */
    public function testIsInSameNamespace($id, $namespace, $expected)
    {
        $result = IdUtils::isInSameNamespace($id, $namespace);
        $this->assertSame($expected, $result);
    }

    /**
     * Test that empty ID throws exception in isInSameNamespace
     */
    public function testIsInSameNamespaceEmptyIdThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Empty ID is not allowed');
        IdUtils::isInSameNamespace('', 'namespace');
    }
}
