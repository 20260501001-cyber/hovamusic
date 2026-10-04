<?php

namespace App\Domain\Media;

use RuntimeException;

class CoverRejected extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
