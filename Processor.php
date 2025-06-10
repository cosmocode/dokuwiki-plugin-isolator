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

    /** @var callable|null Logger function */
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
     *
     * @param string $namespace The namespace to isolate
     */
    public function isolate($namespace = null)
    {
        global $conf;

        $ns = $namespace ?: $this->namespace;
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
            $result = $this->processPage($pageID, $ns);
            $this->results[$pageID] = $result;
            $this->log("\n");
        }
    }

    /**
     * Process a single page
     *
     * @param string $id The page ID
     * @param string $ns The namespace
     * @return array Processing results
     */
    public function processPage($id, $ns = null)
    {
        $namespace = $ns ?: $this->namespace;
        
        // Parse and rewrite the page
        $rewriteResult = $this->parseAndRewritePage($id, $namespace);
        
        // Apply changes if not in dry-run mode
        if (!$this->dryRun) {
            $this->applyChanges($id, $rewriteResult, $namespace);
        }
        
        return $rewriteResult;
    }

    /**
     * Parse and rewrite a page's content
     *
     * @param string $id The page ID
     * @param string $namespace The namespace
     * @return array Rewrite results containing old text, new text, and copy list
     */
    public function parseAndRewritePage($id, $namespace)
    {
        $old = rawWiki($id);

        // Create the parser
        $Parser = new Parser(new Doku_Handler());
        $Handler = new RewriteHandler($id, $namespace, $this->strict);

        // Use reflection to actually use our own handler
        $reflectParser = new ReflectionClass(Parser::class);
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
     * @param string $namespace The namespace
     */
    public function applyChanges($id, $rewriteResult, $namespace)
    {
        // Save new revision if changed
        if ($rewriteResult['changed']) {
            saveWikiText($id, $rewriteResult['new'], 'Isolated media files in namespace ' . $namespace);
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
        if ($this->logger) {
            call_user_func($this->logger, $message);
        } else {
            echo $message;
        }
    }
}
