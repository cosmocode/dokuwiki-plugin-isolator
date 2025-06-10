<?php

namespace dokuwiki\plugin\isolator;

use RuntimeException;

/**
 * Utility class for handling DokuWiki page IDs and namespaces
 */
class IdUtils
{
    /**
     * Check if a page ID is within a specific namespace
     *
     * @param string $id The page ID to check
     * @param string $namespace The namespace to check against
     * @return bool True if the page is in the namespace, false otherwise
     * @throws RuntimeException When an empty ID is provided
     */
    public static function isInNamespace(string $id, string $namespace): bool
    {
        if ($id === '') {
            throw new RuntimeException('Empty ID is not allowed');
        }

        if ($namespace === '') {
            return true; // root namespace
        }

        return str_starts_with($id, "$namespace:");
    }

    /**
     * Check if a page ID is in exactly the same namespace (not a sub-namespace)
     *
     * @param string $id The page ID to check
     * @param string $namespace The namespace to check against
     * @return bool True if the page is in the same namespace, false otherwise
     * @throws RuntimeException When an empty ID is provided
     */
    public static function isInSameNamespace(string $id, string $namespace): bool
    {
        if ($id === '') {
            throw new RuntimeException('Empty ID is not allowed');
        }

        $idns = (string)getNS($id);
        return $idns === $namespace;
    }

    /**
     * Calculate a relative path between two page IDs
     *
     * This method creates a relative path from a reference page to a target page,
     * using DokuWiki's namespace notation with '..' for parent directories.
     *
     * @param string $targetId The target page ID
     * @param string $reference The reference page ID
     * @return string The relative path from reference to target
     */
    public static function getRelativeID(string $targetId, string $reference): string
    {
        $idNS = explode(':', $targetId);
        $id = array_pop($idNS);
        $refNS = explode(':', $reference);
        array_pop($refNS);

        // Remove common namespace parts
        while ($idNS !== [] && $refNS !== [] && $idNS[0] === $refNS[0]) {
            array_shift($idNS);
            array_shift($refNS);
        }

        // Add `..` for each remaining part in the reference namespace
        $relative = str_repeat('..:', count($refNS));

        // Append the remaining parts of the ID
        $relative .= implode(':', $idNS);

        // Remove trailing colon if any
        $relative = rtrim($relative, ':');

        // Add the ID itself
        $relative .= ($relative === '' ? '' : ':') . $id;

        // Ensure we are always relative to the current namespace. This is specific to the isolator plugin
        if (str_contains($relative, ':') && !str_starts_with($relative, '.')) {
            $relative = '.' . $relative;
        }

        return $relative;
    }
}
