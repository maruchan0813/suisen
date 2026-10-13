<?php

declare(strict_types=1);

namespace Suisen\Core\Search;

final class SearchHit
{
    public function __construct(
        public readonly Chunk $chunk,
        public readonly float $score,
    ) {
    }
}
