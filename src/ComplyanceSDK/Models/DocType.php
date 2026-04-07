<?php

namespace ComplyanceSDK\Models;

class DocType
{
    public static function of(string $base, string ...$modifiers): GetsDocumentType
    {
        $builder = GetsDocumentType::builder()->base($base);
        foreach ($modifiers as $modifier) {
            $builder->modifier($modifier);
        }

        return $builder->build();
    }
}
