<?php

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

class BusinessRuleException extends RuntimeException
{
    /**
     * @param  string  $errorCode  Stable machine-readable code, e.g. "player.limit_reached".
     *                             Must exist under `errors` in lang/en/errors.php and lang/ar/errors.php.
     * @param  array<string, mixed>  $params  Replacement values for the translated message.
     * @param  int  $status  HTTP status the API should answer with.
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly array $params = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($errorCode);
    }
}
