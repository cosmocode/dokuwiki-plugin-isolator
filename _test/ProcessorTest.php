<?php

namespace dokuwiki\plugin\isolator\test;

use DokuWikiTest;
use dokuwiki\plugin\isolator\Processor;

/**
 * Tests for the Processor class
 *
 * @group plugin_isolator
 * @group plugins
 */
class ProcessorTest extends DokuWikiTest
{
    public function setUp(): void
    {
        parent::setUp();

        // Create test pages with media references
        saveWikiText('test:page1', '{{other:image4.jpg}} Some content with external media', 'Test setup');
        saveWikiText('test:page2', '{{test:image1.jpg}} Content with local media', 'Test setup');
        saveWikiText('test:subns:page3', '{{test:image2.png}} Content in subnamespace', 'Test setup');
        saveWikiText('other:page4', '{{test:image1.jpg}} Content in other namespace', 'Test setup');

        // Create test media files
        $testMediaFiles = [
            'test:image1.jpg' => 'fake jpg content',
            'test:image2.png' => 'fake png content',
            'test:subns:image3.gif' => 'fake gif content',
            'other:image4.jpg' => 'fake jpg content'
        ];

        foreach ($testMediaFiles as $mediaId => $content) {
            $file = mediaFN($mediaId);
            io_makeFileDir($file);
            file_put_contents($file, $content);
        }
    }

    public function testProcessPageWithExternalMedia()
    {
        $processor = new Processor('test', true, false);
        $result = $processor->processPage('test:page1');

        $this->assertTrue($result['changed']);
        $this->assertNotEmpty($result['copyList']);
        $this->assertArrayHasKey('other:image4.jpg', $result['copyList']);
        $this->assertEquals('test:image4.jpg', $result['copyList']['other:image4.jpg']);

        // Verify the content was actually changed to use relative reference
        $this->assertStringContainsString('{{image4.jpg}}', $result['new']);
        $this->assertStringNotContainsString('{{other:image4.jpg}}', $result['new']);
    }

    public function testProcessPageWithLocalMedia()
    {
        $processor = new Processor('test', true, false);
        $result = $processor->processPage('test:page2');

        // Should not copy since media is already in namespace
        $this->assertEmpty($result['copyList']);

        // But should still adjust reference to be relative if it was absolute
        $this->assertTrue($result['changed']);
        $this->assertStringContainsString('{{image1.jpg}}', $result['new']);
        $this->assertStringNotContainsString('{{test:image1.jpg}}', $result['new']);
    }

    public function testStrictModeVsNonStrict()
    {
        // Non-strict mode: subnamespace media should not be moved
        $processorNonStrict = new Processor('test', true, false);
        $resultNonStrict = $processorNonStrict->processPage('test:subns:page3');

        // Strict mode: subnamespace media should be moved
        $processorStrict = new Processor('test', true, true);
        $resultStrict = $processorStrict->processPage('test:subns:page3');

        // In non-strict mode, test:image2.png should be considered "in namespace"
        // but reference should still be adjusted to relative
        $this->assertTrue($resultNonStrict['changed']);
        $this->assertEmpty($resultNonStrict['copyList']); // No copying in non-strict mode

        // In strict mode, test:image2.png should be moved to test:subns:
        $this->assertTrue($resultStrict['changed']);
        $this->assertNotEmpty($resultStrict['copyList']); // Should copy in strict mode
    }

    public function testIsolateProcessesMultiplePages()
    {
        $processor = new Processor('test', true, false);
        $processor->isolate();

        $results = $processor->getResults();
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        // Should have processed pages in the test namespace
        $processedIds = array_keys($results);
        $testPages = array_filter($processedIds, function($id) {
            return strpos($id, 'test:') === 0;
        });
        $this->assertNotEmpty($testPages);
    }

    public function testUseCaseExample()
    {
        // Create the specific scenario from UseCase.md
        saveWikiText('foo:bar:baz', '{{foo:qux.jpg}} Content with media reference', 'Test setup');

        // Create the media file
        $mediaFile = mediaFN('foo:qux.jpg');
        io_makeFileDir($mediaFile);
        file_put_contents($mediaFile, 'fake jpg content');

        // Test normal mode - should keep media in foo: but adjust reference
        $processor = new Processor('foo', true, false);
        $result = $processor->processPage('foo:bar:baz');

        $this->assertTrue($result['changed']);
        $this->assertEmpty($result['copyList']); // No copying needed in normal mode
        $this->assertStringContainsString('{{..:qux.jpg}}', $result['new']);
        $this->assertStringNotContainsString('{{foo:qux.jpg}}', $result['new']);

        // Test strict mode - should copy media to foo:bar: and adjust reference
        $processorStrict = new Processor('foo', true, true);
        $resultStrict = $processorStrict->processPage('foo:bar:baz');

        $this->assertTrue($resultStrict['changed']);
        $this->assertNotEmpty($resultStrict['copyList']);
        $this->assertArrayHasKey('foo:qux.jpg', $resultStrict['copyList']);
        $this->assertEquals('foo:bar:qux.jpg', $resultStrict['copyList']['foo:qux.jpg']);
        $this->assertStringContainsString('{{qux.jpg}}', $resultStrict['new']);

    }

    public function testAbsolutePathConversion()
    {
        // Create a page with absolute media reference
        saveWikiText('test:subpage', '{{test:image1.jpg}} Content with absolute reference', 'Test setup');

        $processor = new Processor('test', true, false);
        $result = $processor->processPage('test:subpage');

        $this->assertTrue($result['changed']);
        $this->assertEmpty($result['copyList']); // No copying needed, just reference adjustment
        $this->assertStringContainsString('{{image1.jpg}}', $result['new']);
        $this->assertStringNotContainsString('{{test:image1.jpg}}', $result['new']);

    }

    public function testProcessPageWithNoMedia()
    {
        // Create a page without media
        $testPageId = 'test:no_media';
        saveWikiText($testPageId, 'Just some text without media', 'Test setup');

        $processor = new Processor('test', true, false);
        $result = $processor->processPage($testPageId);

        $this->assertFalse($result['changed']);
        $this->assertEmpty($result['copyList']);
    }
}
