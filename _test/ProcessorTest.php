<?php

namespace dokuwiki\plugin\isolator\test;

use DokuWikiTest;
use dokuwiki\plugin\isolator\Processor;
use dokuwiki\Logger;

/**
 * Tests for the Processor class
 *
 * @group plugin_isolator
 * @group plugins
 */
class ProcessorTest extends DokuWikiTest
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean up any existing test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Clean up test pages and media files
     */
    protected function cleanupTestData()
    {
        $testPages = [
            'test:page1',
            'test:page2',
            'test:subns:page3',
            'other:page4'
        ];

        foreach ($testPages as $page) {
            $file = wikiFN($page);
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $testMedia = [
            'test:image1.jpg',
            'test:image2.png',
            'test:subns:image3.gif',
            'other:image4.jpg'
        ];

        foreach ($testMedia as $media) {
            $file = mediaFN($media);
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Create test pages and media files
     */
    protected function createTestData()
    {
        // Create test pages
        saveWikiText('test:page1', '{{other:image4.jpg}} Some content with external media', 'Test setup');
        saveWikiText('test:page2', '{{test:image1.jpg}} Content with local media', 'Test setup');
        saveWikiText('test:subns:page3', '{{other:image4.jpg}} {{test:image2.png}} Mixed media', 'Test setup');
        saveWikiText('other:page4', '{{test:image1.jpg}} Cross-namespace reference', 'Test setup');

        // Create test media files
        $testMedia = [
            'test:image1.jpg' => 'J',
            'test:image2.png' => 'P',
            'test:subns:image3.gif' => 'G',
            'other:image4.jpg' => 'O'
        ];

        foreach ($testMedia as $id => $content) {
            $file = mediaFN($id);
            io_makeFileDir($file);
            file_put_contents($file, $content);
        }
    }

    /**
     * Test basic processor construction
     */
    public function testConstructor()
    {
        $processor = new Processor('test', false, false);
        $this->assertInstanceOf(Processor::class, $processor);

        $processor = new Processor('test', true, true, new Logger());
        $this->assertInstanceOf(Processor::class, $processor);
    }

    /**
     * Data provider for testing different processor configurations
     */
    public function processorConfigProvider()
    {
        return [
            'normal mode, non-strict' => [false, false],
            'dry-run mode, non-strict' => [true, false],
            'normal mode, strict' => [false, true],
            'dry-run mode, strict' => [true, true],
        ];
    }

    /**
     * @dataProvider processorConfigProvider
     */
    public function testProcessPageWithDifferentConfigs($dryRun, $strict)
    {
        $this->createTestData();

        $processor = new Processor('test', $dryRun, $strict);
        $result = $processor->processPage('test:page1');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('old', $result);
        $this->assertArrayHasKey('new', $result);
        $this->assertArrayHasKey('changed', $result);
        $this->assertArrayHasKey('copyList', $result);

        // Should detect that external media needs to be copied
        $this->assertTrue($result['changed']);
        $this->assertNotEmpty($result['copyList']);
    }

    /**
     * Test processing a page with external media that needs isolation
     */
    public function testProcessPageWithExternalMedia()
    {
        $this->createTestData();

        $processor = new Processor('test', true, false); // dry-run mode
        $result = $processor->processPage('test:page1');

        $this->assertTrue($result['changed']);
        $this->assertArrayHasKey('other:image4.jpg', $result['copyList']);
        $this->assertEquals('test:image4.jpg', $result['copyList']['other:image4.jpg']);

        // Check that the wiki text was rewritten with relative path
        $this->assertStringContains('{{.image4.jpg}}', $result['new']);
    }

    /**
     * Test processing a page with local media that doesn't need isolation
     */
    public function testProcessPageWithLocalMedia()
    {
        $this->createTestData();

        $processor = new Processor('test', true, false); // dry-run mode
        $result = $processor->processPage('test:page2');

        // Should not change since media is already in correct namespace
        $this->assertFalse($result['changed']);
        $this->assertEmpty($result['copyList']);
    }

    /**
     * Test strict mode vs non-strict mode
     */
    public function testStrictModeVsNonStrict()
    {
        $this->createTestData();

        // Non-strict: subnamespace media should be allowed
        $processorNonStrict = new Processor('test', true, false);
        $resultNonStrict = $processorNonStrict->processPage('test:subns:page3');

        // Strict: subnamespace media should be moved
        $processorStrict = new Processor('test', true, true);
        $resultStrict = $processorStrict->processPage('test:subns:page3');

        // In strict mode, test:image2.png should be copied because it's not in exact same namespace
        $this->assertGreaterThan(count($resultNonStrict['copyList']), count($resultStrict['copyList']));
    }

    /**
     * Test the isolate method processes multiple pages
     */
    public function testIsolateProcessesMultiplePages()
    {
        $this->createTestData();

        $processor = new Processor('test', true, false); // dry-run mode
        $processor->isolate();

        $results = $processor->getResults();
        $this->assertNotEmpty($results);

        // Should have processed pages in the test namespace
        $processedPages = array_keys($results);
        $this->assertContains('test:page1', $processedPages);
        $this->assertContains('test:page2', $processedPages);
        $this->assertContains('test:subns:page3', $processedPages);
        $this->assertNotContains('other:page4', $processedPages); // Not in test namespace
    }

    /**
     * Test logging with different logger types
     */
    public function testLoggingWithDifferentLoggers()
    {
        $this->createTestData();

        // Test with no logger (should not throw errors)
        $processor = new Processor('test', true, false, null);
        $result = $processor->processPage('test:page1');
        $this->assertIsArray($result);

        // Test with Logger instance
        $logger = new Logger();
        $processor = new Processor('test', true, false, $logger);
        $result = $processor->processPage('test:page1');
        $this->assertIsArray($result);

        // Test with mock CLIPlugin
        $cliPlugin = $this->createMockCLIPlugin();
        $processor = new Processor('test', true, false, $cliPlugin);
        $result = $processor->processPage('test:page1');
        $this->assertIsArray($result);
    }

    /**
     * Test that non-dry-run mode actually applies changes
     */
    public function testNonDryRunAppliesChanges()
    {
        $this->createTestData();

        $originalContent = rawWiki('test:page1');
        
        $processor = new Processor('test', false, false); // non-dry-run mode
        $result = $processor->processPage('test:page1');

        if ($result['changed']) {
            $newContent = rawWiki('test:page1');
            $this->assertNotEquals($originalContent, $newContent);
            
            // Check that media files were copied
            foreach ($result['copyList'] as $from => $to) {
                $this->assertFileExists(mediaFN($to));
            }
        }
    }

    /**
     * Test that dry-run mode doesn't apply changes
     */
    public function testDryRunDoesNotApplyChanges()
    {
        $this->createTestData();

        $originalContent = rawWiki('test:page1');
        
        $processor = new Processor('test', true, false); // dry-run mode
        $result = $processor->processPage('test:page1');

        $contentAfter = rawWiki('test:page1');
        $this->assertEquals($originalContent, $contentAfter);

        // Check that media files were not copied
        foreach ($result['copyList'] as $from => $to) {
            $this->assertFileDoesNotExist(mediaFN($to));
        }
    }

    /**
     * Test processing page with no media references
     */
    public function testProcessPageWithNoMedia()
    {
        saveWikiText('test:simple', 'Just some text without media', 'Test setup');

        $processor = new Processor('test', true, false);
        $result = $processor->processPage('test:simple');

        $this->assertFalse($result['changed']);
        $this->assertEmpty($result['copyList']);
        $this->assertEquals($result['old'], $result['new']);
    }

    /**
     * Create a mock CLI plugin for testing
     */
    protected function createMockCLIPlugin()
    {
        return new class extends \dokuwiki\Extension\CLIPlugin {
            public function info($message) {
                // Mock implementation - just store the message
            }
            
            protected function setup(\splitbrain\phpcli\Options $options) {
                // Mock implementation
            }
            
            protected function main(\splitbrain\phpcli\Options $options) {
                // Mock implementation
            }
        };
    }
}
