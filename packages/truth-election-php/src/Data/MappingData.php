<?php

namespace TruthElection\Data;

use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Data;

/**
 * @property-read string $code
 * @property-read string $location_name
 * @property-read string $district
 * @property-read DataCollection<MarkData> $marks
 */
class MappingData extends Data
{
    /**
     * @param string $code
     * @param string $location_name
     * @param string $district
     * @param DataCollection $marks
     */
    public function __construct(
        public string $code,
        public string $location_name,
        public string $district,
        public DataCollection $marks,
    ) {}
}
