<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AmbiguousCastawayException extends LeagueException
{
    /**
     * @param  array<int, string>  $candidates
     */
    public function __construct(public readonly string $query, public readonly array $candidates)
    {
        parent::__construct(sprintf(
            '"%s" matches more than one castaway: %s. Which one did you mean?',
            $query,
            implode(', ', $candidates),
        ));
    }
}
