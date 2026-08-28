<?php

declare(strict_types=1);

namespace App\Exceptions;

final class SeasonNotFoundException extends LeagueException
{
    public static function active(): self
    {
        return new self('There is no active season. Create one, or pass a season number.');
    }

    public static function numbered(int $number): self
    {
        return new self("There is no season {$number}.");
    }
}
