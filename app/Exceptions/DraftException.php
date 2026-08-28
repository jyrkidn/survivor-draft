<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DraftException extends LeagueException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
