<?php

namespace dokuwiki\plugin\isolator;

use dokuwiki\File\MediaResolver;
use dokuwiki\Logger;

class RewriteHandler
{
    public $calls = [];

    /**
     * @var string This handler does not create calls, but rather recreates wiki text in one go.
     */
    protected $wikitext = '';

    /**
     * @var string The ID of the page being processed
     */
    protected $id;

    /**
     * @var string The namespace we're processing
     */
    protected $ns;

    /**
     * @var bool Whether to use strict mode (exact namespace match)
     */
    protected $strict;

    /** @var array The media files to copy */
    protected $toCopy = [];

    /**
     * @var MediaResolver
     */
    protected $mediaResolver;

    public function __construct($id, $namespace, $strict = false)
    {
        $this->id = $id;
        $this->ns = $namespace;
        $this->strict = $strict;
        $this->mediaResolver = new MediaResolver($id);
    }

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
                $match = preg_replace('/' . preg_quote($mediaID, '/') . '/', $relative, $match);
            }
        } else {
            Logger::error(
                'Failed to extract media ID from match: ' . $match
            );
        }

        $this->wikitext .= $match;
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
            Logger::error(
                'Error, handler function ' . $name . ' with ' . count($params) .
                ' parameters called which isn\'t implemented'
            );
            return false;
        }
    }

    public function finalize()
    {
        // remove padding that is added by the parser in parse()
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
