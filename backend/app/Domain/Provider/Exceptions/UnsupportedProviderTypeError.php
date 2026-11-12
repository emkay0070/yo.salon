<?php

namespace App\Domain\Provider\Exceptions;

use Exception;

class UnsupportedProviderTypeError extends Exception
{
    public function __construct(string $type)
    {
        parent::__construct("Provider type '{$type}' is not supported.");
    }
}
