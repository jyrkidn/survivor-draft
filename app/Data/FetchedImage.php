<?php

declare(strict_types=1);

namespace App\Data;

final readonly class FetchedImage
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public string $extension,
    ) {}
}
