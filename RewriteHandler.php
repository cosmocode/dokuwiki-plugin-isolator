<?php

namespace dokuwiki\plugin\isolator;

use dokuwiki\Extension\CLIPlugin;
use dokuwiki\File\MediaResolver;
use dokuwiki\Logger;

/**
 * Handler for rewriting DokuWiki syntax to isolate media files within namespaces
 *
 * This class processes DokuWiki markup and rewrites media references to ensure
 * they are properly isolated within the target namespace. It handles both strict
 * and non-strict modes for namespace isolation.
 *
 * This class is heavily inspired by the move plugin's rewriting mechanism, implementing a
 * similar fake DokuHandler.
 */
class RewriteHandler
{
    /** @var array Callstack for compatibility with DokuHandler */
    public $calls = [];

    /** @var string This handler does not create calls, but rather recreates wiki text in one go. */
    protected $wikitext = '';

    /** @var string The ID of the page being processed */
    protected $id;

    /** @var string The namespace we're processing */
    protected $ns;

    /** @var bool Whether to use strict mode (exact namespace match) */
    protected $strict;

    /** @var array The media files to copy */
    protected $toCopy = [];

    /** @var MediaResolver */
    protected $mediaResolver;

    /** @var CLIPlugin|null Logger instance */
    protected $logger;

    /**
     * Constructor
     *
     * @param string $id The ID of the page being processed
     * @param string $namespace The namespace we're processing
     * @param bool $strict Whether to use strict mode (exact namespace match)
     * @param CLIPlugin|null $logger Logger instance for output
     */
    public function __construct($id, $namespace, $strict = false, ?CLIPlugin $logger = null)
    {
        $this->id = $id;
        $this->ns = $namespace;
        $this->strict = $strict;
        $this->logger = $logger;
        $this->mediaResolver = new MediaResolver($id);
    }

    /**
     * Handle media syntax and rewrite media references
     *
     * @param string $match The matched text
     * @param int $state The parser state
     * @param int $pos The position in the text
     * @return bool Always returns true to continue parsing
     */
    public function media($match, $state, $pos)
    {
        if (preg_match('/\{\{\s*([^?|\s}]+)/', $match, $extract)) {
            $mediaID = $extract[1];
            if (!preg_match('/^(https?:\/\/)/', $mediaID)) {
                $resolved = $this->mediaResolver->resolveId($mediaID);

                $targetNamespace = $this->strict
                    ? getNS($this->id)
                    : $this->ns;

                if (!IdUtils::isInNamespace($resolved, $targetNamespace)) {
                    $local = $targetNamespace . ':' . noNS($resolved);
                    $this->toCopy[$resolved] = $local;
                } else {
                    $local = $resolved;
                }

                $relative = IdUtils::getRelativeID($local, $this->id);
                if ($mediaID != $relative) {
                    $debug = "Adjusting media ID '$mediaID' to relative '$relative'";
                    $this->logger ? $this->logger->debug($debug) : Logger::debug($debug);
                    $match = preg_replace('/' . preg_quote($mediaID, '/') . '/', $relative, $match);
                }
            }
        } else {
            $error = 'Failed to extract media ID from match: ' . $match;
            $this->logger ? $this->logger->error($error) : Logger::error($error);
        }

        $this->wikitext .= $match;
        return true;
    }

    /**
     * Catchall handler for the remaining syntax
     *
     * @param string $name Function name that was called
     * @param array $params Original parameters
     * @return bool If parsing should be continue
     */
    public function __call($name, $params)
    {
        if (count($params) == 3) {
            $this->wikitext .= $params[0];
            return true;
        } else {
            $error = 'Error, handler function ' . $name . ' with ' . count($params) .
                ' parameters called which isn\'t implemented';
            $this->logger ? $this->logger->error($error) : Logger::error($error);
            return false;
        }
    }

    /**
     * Handle rewriting of plugin syntax, calls the registered handlers
     *
     * @param string $match The text match
     * @param string $state The starte of the parser
     * @param int $pos The position in the input
     * @param string $pluginname The name of the plugin
     * @return bool If parsing should be continued
     */
    public function plugin($match, $state, $pos, $pluginname)
    {
        if (isset($this->handlers[$pluginname])) {
            $this->wikitext .= call_user_func($this->handlers[$pluginname], $match, $state, $pos, $pluginname, $this);
        } else {
            $this->wikitext .= $match;
        }
        return true;
    }

    /**
     * Finalize the rewriting process
     *
     * Removes padding that is added by the parser during parse()
     */
    public function finalize()
    {
        $this->wikitext = preg_replace('/^\n|\n$/', '', $this->wikitext);
    }

    /**
     * Get the rewritten wiki text
     *
     * @return string The rewritten wiki text
     */
    public function getWikiText()
    {
        return $this->wikitext;
    }

    /**
     * Get the list of media files that need to be copied
     *
     * @return array An associative array of media files to copy, keys are source IDs and values are destination IDs
     */
    public function getCopyList()
    {
        return $this->toCopy;
    }
}
