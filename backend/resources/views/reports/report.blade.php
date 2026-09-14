<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 24px; margin-bottom: 8px; }
        p.period { color: #6b7280; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
        td.numeric, th.numeric { text-align: right; }
    </style>
</head>
<body>
    <h1>Bakkerij X - Workforce Report</h1>
    <p class="period">{{ $period->startDate->toDateString() }} to {{ $period->endDate->toDateString() }}</p>

    <h2>Headcount per line per shift per day</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Line</th>
                <th>Shift</th>
                <th class="numeric">Headcount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($headcount as $row)
                <tr>
                    <td>{{ $row->workDate }}</td>
                    <td>{{ $row->lineName }}</td>
                    <td>{{ $row->shiftPatternName }}</td>
                    <td class="numeric">{{ $row->headcount }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No approved schedule data in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Cost breakdown</h2>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Agency</th>
                <th class="numeric">Total hours</th>
                <th class="numeric">Total cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($costBreakdown as $row)
                <tr>
                    <td>{{ $row->employeeType === 'vast' ? 'Permanent' : 'Agency' }}</td>
                    <td>{{ $row->agencyName ?? '—' }}</td>
                    <td class="numeric">{{ number_format($row->totalHours, 2) }}</td>
                    <td class="numeric">{{ number_format($row->totalCost, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No approved schedule data in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
