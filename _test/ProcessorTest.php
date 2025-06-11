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
        saveWikiText('test:page1', '{{other:document4.pdf}} Some content with external media', 'Test setup');
        saveWikiText('test:page2', '{{test:document1.pdf}} Content with local media', 'Test setup');
        saveWikiText('test:subns:page3', '{{test:document2.pdf}} Content in subnamespace', 'Test setup');
        saveWikiText('other:page4', '{{test:document1.pdf}} Content in other namespace', 'Test setup');

        // Create test media files
        $testMediaFiles = [
            'test:document1.pdf' => 'fake media content',
            'test:document2.pdf' => 'fake media content',
            'test:subns:document3.pdf' => 'fake media content',
            'other:document4.pdf' => 'fake media content'
        ];

        foreach ($testMediaFiles as $mediaId => $content) {
            $file = mediaFN($mediaId);
            io_makeFileDir($file);
            file_put_contents($file, $content);
        }
    }

    public function testProcessPageWithExternalMedia()
    {
        $processor = new Processor('test', false, false);
        $result = $processor->processPage('test:page1');

        $this->assertTrue($result['changed']);
        $this->assertNotEmpty($result['copyList']);
        $this->assertArrayHasKey('other:document4.pdf', $result['copyList']);
        $this->assertEquals('test:document4.pdf', $result['copyList']['other:document4.pdf']);

        // Verify the content was actually changed to use relative reference
        $this->assertStringContainsString('{{document4.pdf}}', $result['new']);
        $this->assertStringNotContainsString('{{other:document4.pdf}}', $result['new']);

        // Verify the media file was actually copied
        $copiedFile = mediaFN('test:document4.pdf');
        $this->assertFileExists($copiedFile);
        $this->assertEquals('fake media content', file_get_contents($copiedFile));
    }

    public function testProcessPageWithLocalMedia()
    {
        $processor = new Processor('test', false, false);
        $result = $processor->processPage('test:page2');

        // Should not copy since media is already in namespace
        $this->assertEmpty($result['copyList']);

        // But should still adjust reference to be relative if it was absolute
        $this->assertTrue($result['changed']);
        $this->assertStringContainsString('{{document1.pdf}}', $result['new']);
        $this->assertStringNotContainsString('{{test:document1.pdf}}', $result['new']);

        // Verify original media file still exists and wasn't moved
        $originalFile = mediaFN('test:document1.pdf');
        $this->assertFileExists($originalFile);
    }

    public function testStrictModeVsNonStrict()
    {
        // Non-strict mode: subnamespace media should not be moved
        $processorNonStrict = new Processor('test', false, false);
        $resultNonStrict = $processorNonStrict->processPage('test:subns:page3');

        // Strict mode: subnamespace media should be moved
        $processorStrict = new Processor('test', false, true);
        $resultStrict = $processorStrict->processPage('test:subns:page3');

        // In non-strict mode, test:document2.pdf should be considered "in namespace"
        // but reference should still be adjusted to relative
        $this->assertTrue($resultNonStrict['changed']);
        $this->assertEmpty($resultNonStrict['copyList']); // No copying in non-strict mode

        // In strict mode, test:document2.pdf should be moved to test:subns:
        $this->assertTrue($resultStrict['changed']);
        $this->assertNotEmpty($resultStrict['copyList']); // Should copy in strict mode

        // Verify the media file was actually copied in strict mode
        $copiedFile = mediaFN('test:subns:document2.pdf');
        $this->assertFileExists($copiedFile);
        $this->assertEquals('fake media content', file_get_contents($copiedFile));
    }

    public function testIsolateProcessesMultiplePages()
    {
        $processor = new Processor('test', false, false);
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

        // Verify that media files were actually copied where needed
        $copiedFile = mediaFN('test:document4.pdf');
        $this->assertFileExists($copiedFile);
    }

    public function testUseCaseExample()
    {
        // Create the specific scenario from UseCase.md
        saveWikiText('foo:bar:baz', '{{foo:qux.pdf}} Content with media reference', 'Test setup');

        // Create the media file
        $mediaFile = mediaFN('foo:qux.pdf');
        io_makeFileDir($mediaFile);
        file_put_contents($mediaFile, 'fake media content');

        // Test normal mode - should keep media in foo: but adjust reference
        $processor = new Processor('foo', false, false);
        $result = $processor->processPage('foo:bar:baz');

        $this->assertTrue($result['changed']);
        $this->assertEmpty($result['copyList']); // No copying needed in normal mode
        $this->assertStringContainsString('{{..:qux.pdf}}', $result['new']);
        $this->assertStringNotContainsString('{{foo:qux.pdf}}', $result['new']);

        // Test strict mode - should copy media to foo:bar: and adjust reference
        $processorStrict = new Processor('foo', false, true);
        $resultStrict = $processorStrict->processPage('foo:bar:baz');

        $this->assertTrue($resultStrict['changed']);
        $this->assertNotEmpty($resultStrict['copyList']);
        $this->assertArrayHasKey('foo:qux.pdf', $resultStrict['copyList']);
        $this->assertEquals('foo:bar:qux.pdf', $resultStrict['copyList']['foo:qux.pdf']);
        $this->assertStringContainsString('{{qux.pdf}}', $resultStrict['new']);

        // Verify the media file was actually copied in strict mode
        $copiedFile = mediaFN('foo:bar:qux.pdf');
        $this->assertFileExists($copiedFile);
        $this->assertEquals('fake media content', file_get_contents($copiedFile));

    }

    public function testAbsolutePathConversion()
    {
        // Create a page with absolute media reference
        saveWikiText('test:subpage', '{{test:document1.pdf}} Content with absolute reference', 'Test setup');

        $processor = new Processor('test', false, false);
        $result = $processor->processPage('test:subpage');

        $this->assertTrue($result['changed']);
        $this->assertEmpty($result['copyList']); // No copying needed, just reference adjustment
        $this->assertStringContainsString('{{document1.pdf}}', $result['new']);
        $this->assertStringNotContainsString('{{test:document1.pdf}}', $result['new']);

        // Verify original media file still exists
        $originalFile = mediaFN('test:document1.pdf');
        $this->assertFileExists($originalFile);
    }

    public function testProcessPageWithNoMedia()
    {
        // Create a page without media
        $testPageId = 'test:no_media';
        saveWikiText($testPageId, 'Just some text without media', 'Test setup');

        $processor = new Processor('test', false, false);
        $result = $processor->processPage($testPageId);

        $this->assertFalse($result['changed']);
        $this->assertEmpty($result['copyList']);
    }
}
