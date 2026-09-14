<?php

namespace App\Modules\Reporting\DTOs;

final readonly class HeadcountRowData
{
    public function __construct(
        public string $workDate,
        public int $lineId,
        public string $lineName,
        public int $shiftPatternId,
        public string $shiftPatternName,
        public int $headcount,
    ) {}
}
