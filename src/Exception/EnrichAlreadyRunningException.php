<?php

namespace App\Exception;

final class EnrichAlreadyRunningException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Enrichment is already running.');
    }
}
