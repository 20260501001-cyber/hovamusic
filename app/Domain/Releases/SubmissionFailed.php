<?php

namespace App\Domain\Releases;

use RuntimeException;

class SubmissionFailed extends RuntimeException
{
    /**
     * @param  array<int|string, list<string>>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Yayın gönderilemedi.');
    }
}
