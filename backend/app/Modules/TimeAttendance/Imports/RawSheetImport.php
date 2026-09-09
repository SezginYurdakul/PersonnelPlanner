<?php

namespace App\Modules\TimeAttendance\Imports;

use Maatwebsite\Excel\Concerns\Import;

/**
 * A bare marker Import - Excel::toArray() only needs an instance of this interface to know
 * how to read a file; this module never uses the row-mapping callback-based API (ToArray,
 * OnEachRow, etc.), it always works directly with the plain nested array Excel::toArray()
 * returns (sheet index => rows => columns).
 */
class RawSheetImport implements Import
{
}
