<?php

namespace dokuwiki\plugin\isolator;

use RuntimeException;

class IdUtils
{
    public static function isInNamespace($id, $namespace)
    {
        if ($id === '') {
            throw new RuntimeException('Empty ID is not allowed');
        }

        if ($namespace === '') {
            return true; // root namespace
        }

        return str_starts_with($id, "$namespace:");
    }

    public static function isInSameNamespace($id, $namespace)
    {
        if ($id === '') {
            throw new RuntimeException('Empty ID is not allowed');
        }

        $idns = (string)getNS($id);
        return $idns === $namespace;
    }

    public static function getRelativeID($targetId, $reference)
    {
        $idNS = explode(':', $targetId);
        $id = array_pop($idNS);
        $refNS = explode(':', $reference);
        array_pop($refNS);

        // Remove common namespace parts
        while (!empty($idNS) && !empty($refNS) && $idNS[0] === $refNS[0]) {
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
        if ($relative[0] !== '.') $relative = '.' . $relative;

        return $relative;
    }
}
