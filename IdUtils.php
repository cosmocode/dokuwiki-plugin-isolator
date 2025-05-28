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

        $idns = getNS($id);
        return $idns === $namespace;
    }

    public static function getRelativeID($id, $reference)
    {
        $idNS = explode(':', $id);
        $refNS = explode(':', $reference);

        // Remove common namespace parts
        while (!empty($idNS) && !empty($refNS) && $idNS[0] === $refNS[0]) {
            array_shift($idNS);
            array_shift($refNS);
        }

        // Add `..` for each remaining part in the reference namespace
        $relative = str_repeat('..:', count($refNS));

        // Append the remaining parts of the ID
        $relative .= implode(':', $idNS);

        return $relative;
    }
}
