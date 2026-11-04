<?php

namespace App\Support;

class Like
{
    /** Escapes LIKE wildcards so user input is matched literally (and bounds its length). */
    public static function escape(string $value): string
    {
        return addcslashes(mb_substr(trim($value), 0, 80), '%_\\');
    }
}
