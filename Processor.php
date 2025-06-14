<?php

namespace dokuwiki\plugin\isolator;

use dokuwiki\Extension\CLIPlugin;
use dokuwiki\Parsing\Parser;

/**
 * Main processor for isolating media files within DokuWiki namespaces
 *
 * This class handles the complete workflow of processing pages within a namespace,
 * rewriting media references, and copying media files to ensure proper isolation.
 * Supports both dry-run and strict modes for different use cases.
 */
class Processor
{
    /** @var string The namespace to process */
    protected $namespace;

    /** @var bool Whether to run in dry-run mode */
    protected $dryRun;

    /** @var bool Whether to use strict mode */
    protected $strict;

    /** @var CLIPlugin|null Logger instance */
    protected $logger;

    /** @var array Results from processing */
    protected $results = [];

    public function __construct($namespace, $dryRun = false, $strict = false, ?CLIPlugin $logger = null)
    {
        $this->namespace = $namespace;
        $this->dryRun = $dryRun;
        $this->strict = $strict;
        $this->logger = $logger;
    }

    /**
     * Isolate media files in the given namespace
     */
    public function isolate()
    {
        global $conf;

        $ns = $this->namespace;
        $pagedir = $conf['datadir'];
        $namespacedir = utf8_encodeFN($ns);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator("$pagedir/$namespacedir", \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $fullPath = $file->getPathname();
            if (!preg_match('/\.txt$/', $fullPath)) continue; // only process text files
            $relativePath = preg_replace('/^' . preg_quote("$pagedir/", '/') . '/', '', $fullPath);
            $pageID = pathID($relativePath);

            $result = $this->processPage($pageID);
            $this->results[$pageID] = $result;
        }
    }

    /**
     * Process a single page
     *
     * @param string $id The page ID
     * @return array Processing results
     */
    public function processPage($id)
    {
        $this->log('notice', "Processing page '$id'");
        $rewriteResult = $this->parseAndRewritePage($id);
        $this->applyChanges($id, $rewriteResult);
        return $rewriteResult;
    }

    /**
     * Parse and rewrite a page's content
     *
     * @param string $id The page ID
     * @return array Rewrite results containing old text, new text, and copy list
     */
    public function parseAndRewritePage($id)
    {
        $old = rawWiki($id);

        // Create the parser
        $Parser = new Parser(new \Doku_Handler());
        $Handler = new RewriteHandler($id, $this->namespace, $this->strict, $this->logger);

        // Use reflection to actually use our own handler
        $reflectParser = new \ReflectionClass(Parser::class);
        $handlerProperty = $reflectParser->getProperty('handler');
        $handlerProperty->setAccessible(true);
        $handlerProperty->setValue($Parser, $Handler);

        //add modes to parser
        $modes = p_get_parsermodes();
        foreach ($modes as $mode) {
            $Parser->addMode($mode['mode'], $mode['obj']);
        }

        // parse
        $Parser->parse($old);
        $new = $Handler->getWikiText();

        return [
            'old' => $old,
            'new' => $new,
            'changed' => trim($new) != trim($old),
            'copyList' => $Handler->getCopyList()
        ];
    }

    /**
     * Apply changes to files (save wiki text and copy media)
     *
     * @param string $id The page ID
     * @param array $rewriteResult The rewrite results
     */
    public function applyChanges($id, $rewriteResult)
    {
        // Save new revision if changed
        if ($rewriteResult['changed']) {
            $this->log('info', "Rewriting media references in page '$id'");

            // calculate a diff only if debug is enabled
            if ($this->logger instanceof CLIPlugin && $this->logger->isLogLevelEnabled('debug')) {
                $diff = new \Diff(
                    explode("\n", $rewriteResult['old']),
                    explode("\n", $rewriteResult['new'])
                );
                $unified = new \UnifiedDiffFormatter(1);
                $diffText = $unified->format($diff);
                $this->log('debug', $diffText);
            }

            if (!$this->dryRun) {
                saveWikiText($id, $rewriteResult['new'], 'Isolated media files in namespace ' . $this->namespace);
            }
        }

        // Copy media files
        foreach ($rewriteResult['copyList'] as $from => $to) {
            $this->log('info', "Copying media file '$from' to '$to'");
            if (!$this->dryRun) {
                media_save(['name' => mediaFN($from)], $to, false, AUTH_ADMIN, 'copy');
            }
        }
    }

    /**
     * Get processing results
     *
     * @return array The processing results
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Log a message if a logger is set
     *
     * @param string $level
     * @param string $message The message to log
     * @param array $context
     */
    protected function log($level, $message, $context = [])
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
