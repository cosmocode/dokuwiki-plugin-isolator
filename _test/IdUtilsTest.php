<?php

namespace dokuwiki\plugin\isolator\test;

use DokuWikiTest;
use dokuwiki\plugin\isolator\IdUtils;
use RuntimeException;

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

    /**
     * Data provider for testing getRelativeID method
     *
     * @return array Test cases
     */
    public function getRelativeIDProvider()
    {
        return [
            'same page' => [
                'wiki:page', 'wiki:page', 'page'
            ],
            'same namespace different page' => [
                'wiki:target', 'wiki:source', 'target'
            ],
            'child namespace' => [
                'wiki:child:page', 'wiki:source', '.child:page'
            ],
            'parent namespace' => [
                'wiki:page', 'wiki:child:source', '..:page'
            ],
            'sibling namespace' => [
                'wiki:sibling:page', 'wiki:child:source', '..:sibling:page'
            ],
            'multiple levels up' => [
                'wiki:page', 'wiki:child:grandchild:source', '..:..:page'
            ],
            'completely different namespace' => [
                'other:page', 'wiki:source', '..:other:page'
            ],
            'root to namespaced' => [
                'wiki:page', 'source', '.wiki:page'
            ],
            'namespaced to root' => [
                'page', 'wiki:source', '..:page'
            ],
            'deep to deep with common ancestor' => [
                'common:path1:path2:target', 'common:path3:path4:source', '..:..:path1:path2:target'
            ],
        ];
    }

    /**
     * @dataProvider getRelativeIDProvider
     */
    public function testGetRelativeID($id, $reference, $expected)
    {
        $result = IdUtils::getRelativeID($id, $reference);
        $this->assertSame($expected, $result);
    }
}
