<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CastawayNotFoundException extends LeagueException
{
    public static function forName(string $name): self
    {
        return new self("No castaway matches \"{$name}\".");
    }
}
