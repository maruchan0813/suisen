<?php

declare(strict_types=1);

namespace Suisen\Core;

final class Senior
{
    public const CAREER_EMPLOYMENT = 'employment';
    public const CAREER_FURTHER_STUDY = 'further_study';

    public function __construct(
        public readonly int $id,
        public readonly string $courseName,
        public readonly FeatureVector $features,
        public readonly string $careerType,
        public readonly string $destination,
    ) {
    }
}
