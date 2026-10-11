<?php
declare(strict_types=1);

namespace SJT\Core;

/** Carries field => message errors back to a form. */
final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed: ' . implode('; ', $errors));
    }
}
