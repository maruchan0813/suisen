<?php

declare(strict_types=1);

namespace Suisen\Core;

final class Course
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly FeatureVector $features,
        public readonly int $displayOrder,
    ) {
    }
}
