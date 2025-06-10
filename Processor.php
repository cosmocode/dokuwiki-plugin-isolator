<?php

namespace dokuwiki\plugin\isolator;

use dokuwiki\Parsing\Parser;
use dokuwiki\plugin\isolator\RewriteHandler;

class Processor
{
    /** @var string The namespace to process */
    protected $namespace;

    /** @var bool Whether to run in dry-run mode */
    protected $dryRun;

    /** @var bool Whether to use strict mode */
    protected $strict;

    /** @var \dokuwiki\Extension\CLIPlugin|\dokuwiki\Logger|null Logger instance */
    protected $logger;

    /** @var array Results from processing */
    protected $results = [];

    public function __construct($namespace, $dryRun = false, $strict = false, $logger = null)
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

            $this->log($pageID);
            $result = $this->processPage($pageID);
            $this->results[$pageID] = $result;
            $this->log("\n");
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
        // Parse and rewrite the page
        $rewriteResult = $this->parseAndRewritePage($id);

        // Apply changes if not in dry-run mode
        if (!$this->dryRun) {
            $this->applyChanges($id, $rewriteResult);
        }

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
        $Handler = new RewriteHandler($id, $this->namespace, $this->strict);

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
            'changed' => $new != $old,
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
            saveWikiText($id, $rewriteResult['new'], 'Isolated media files in namespace ' . $this->namespace);
        }

        // Copy media files
        foreach ($rewriteResult['copyList'] as $from => $to) {
            media_save(['name' => mediaFN($from)], $to, false, AUTH_ADMIN, 'copy');
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
     * Log a message
     *
     * @param string $message The message to log
     */
    protected function log($message)
    {
        if ($this->logger === null) {
            echo $message;
            return;
        }

        if ($this->logger instanceof \dokuwiki\Extension\CLIPlugin) {
            // CLIPlugin has info() method for logging
            $this->logger->info($message);
        } elseif ($this->logger instanceof \dokuwiki\Logger) {
            // Logger has log() method
            $this->logger->log($message);
        } else {
            // Fallback for any other type
            echo $message;
        }
    }
}
