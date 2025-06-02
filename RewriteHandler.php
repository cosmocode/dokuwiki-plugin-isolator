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
     * @var MediaResolver
     */
    protected $mediaResolver;

    public function __construct($id, $namespace)
    {
        $this->id = $id;
        $this->ns = $namespace;
        $this->mediaResolver = new MediaResolver($id);
    }


    public function media($match, $state, $pos)
    {

        if (preg_match('/\{\{\s*([^?|\s}]+)/', $match, $extract)) {
            $mediaID = $extract[1];
            if (!preg_match('/^(https?:\/\/)/', $mediaID)) {
                $resolved = $this->mediaResolver->resolveId($mediaID);

                echo "\nMedia ID: $mediaID\n";

                if (!IdUtils::isInNamespace($resolved, $this->ns)) {
                    $local = $this->ns .= ':' . getID($resolved);

                    echo 'Media is not in namespace ' . $this->ns . ', copying it to: ' . $local . "\n";
                } else {
                    $local = $resolved;
                }

                $relative = IdUtils::getRelativeID($local, $this->id);
                echo 'Relative media ID: ' . $relative . "\n";

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
        $this->wikitext = substr($this->wikitext, 1, -1);
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
}
