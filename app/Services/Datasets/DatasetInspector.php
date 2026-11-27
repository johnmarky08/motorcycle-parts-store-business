<?php

namespace App\Services\Datasets;

/**
 * Streams a CSV once to validate it, compute quality statistics and a small analysis summary,
 * and serves paginated, searchable previews. Nothing here executes or evaluates cell contents.
 */
class DatasetInspector
{
    public const MAX_RECORDS = 300000;

    private const MAX_LINE = 65536;

    public const DEFINITIONS = [
        'sales_history' => [
            'required' => ['transaction_number', 'transaction_date', 'product_id', 'product_name', 'quantity', 'unit_price', 'total_amount'],
            'numeric' => ['product_id', 'quantity', 'unit_price', 'total_amount'],
            'date' => 'transaction_date',
            'metric' => 'total_amount',
            'units' => 'quantity',
            'target' => 'total_amount',
            'features' => ['transaction_date', 'product_id', 'quantity', 'unit_price'],
        ],
        'product_demand' => [
            'required' => ['week_start', 'product_id', 'product_name', 'category', 'units_sold'],
            'numeric' => ['product_id', 'units_sold'],
            'date' => 'week_start',
            'metric' => 'units_sold',
            'units' => 'units_sold',
            'target' => 'units_sold',
            'features' => ['week_start', 'product_id', 'category', 'avg_unit_price'],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $path, string $type): array
    {
        $def = self::DEFINITIONS[$type] ?? throw new DatasetException('Unknown dataset type.');
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new DatasetException('The file could not be read.');
        }

        try {
            $header = $this->readHeader($handle);
            $missingColumns = array_values(array_diff($def['required'], $header));
            if ($missingColumns) {
                throw new DatasetException('Missing required column(s): '.implode(', ', $missingColumns).'.');
            }

            $index = array_flip($header);
            $width = count($header);
            $dateIdx = $index[$def['date']];
            $metricIdx = $index[$def['metric']];
            $unitsIdx = $index[$def['units']];
            $nameIdx = $index['product_name'];
            $idIdx = $index['product_id'];
            $numericIdx = array_map(fn ($c) => $index[$c], $def['numeric']);
            $revenueIdx = $index['revenue'] ?? null;

            $records = $missing = $duplicates = $invalid = 0;
            $seen = [];
            $min = $max = null;
            $monthly = [];
            $byDate = [];
            $products = [];
            $sample = null;

            while (($row = fgetcsv($handle, self::MAX_LINE)) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (++$records > self::MAX_RECORDS) {
                    throw new DatasetException('The file has more than '.number_format(self::MAX_RECORDS).' records.');
                }
                $sample ??= $row;
                if (count($row) !== $width) {
                    $invalid++;

                    continue;
                }
                foreach ($row as $cell) {
                    if (trim((string) $cell) === '') {
                        $missing++;
                    }
                }
                $hash = md5(implode("\x1f", $row));
                if (isset($seen[$hash])) {
                    $duplicates++;
                }
                $seen[$hash] = true;

                $date = substr(trim($row[$dateIdx]), 0, 10);
                if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    $invalid++;

                    continue;
                }
                $bad = false;
                foreach ($numericIdx as $i) {
                    if (! is_numeric($row[$i]) || (float) $row[$i] < 0) {
                        $bad = true;
                        break;
                    }
                }
                if ($bad) {
                    $invalid++;

                    continue;
                }

                $min = $min === null || $date < $min ? $date : $min;
                $max = $max === null || $date > $max ? $date : $max;
                $month = substr($date, 0, 7);
                $monthly[$month] = ($monthly[$month] ?? 0) + (float) $row[$metricIdx];
                $byDate[$date] = ($byDate[$date] ?? 0) + (float) $row[$metricIdx];

                $key = (int) $row[$idIdx];
                $products[$key] ??= ['name' => (string) $row[$nameIdx], 'units' => 0.0, 'revenue' => 0.0];
                $products[$key]['units'] += (float) $row[$unitsIdx];
                $products[$key]['revenue'] += $type === 'sales_history' ? (float) $row[$metricIdx] : ($revenueIdx !== null ? (float) $row[$revenueIdx] : 0.0);
            }
        } finally {
            fclose($handle);
        }

        $valid = $records - $invalid;
        $min_records = (int) config('store.ml.min_records');
        if ($records < $min_records) {
            throw new DatasetException("The file has {$records} records; at least ".number_format($min_records).' are required.');
        }
        if ($records > 0 && $invalid / $records > 0.05) {
            throw new DatasetException("Too many invalid rows ({$invalid} of {$records}). Check dates (YYYY-MM-DD) and numeric columns.");
        }

        ksort($monthly);
        $status = ($missing > 0 || $duplicates > 0 || $invalid > 0) ? 'warning' : 'validated';

        return [
            'records' => $records,
            'columns' => $width,
            'coverage_start' => $min,
            'coverage_end' => $max,
            'missing_values' => $missing,
            'duplicate_rows' => $duplicates,
            'invalid_rows' => $invalid,
            'status' => $status,
            'target' => $def['target'],
            'features' => array_values(array_intersect($def['features'], $header)),
            'schema' => $this->schema($header, $sample ?? []),
            'summary' => $this->summary($monthly, $byDate, $products, $type, $max),
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, string>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function preview(string $path, int $page = 1, int $perPage = 20, string $search = ''): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return ['columns' => [], 'rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'last_page' => 1];
        }

        $header = $this->readHeader($handle);
        $needle = mb_strtolower(trim($search));
        $rows = [];
        $total = 0;
        $skip = ($page - 1) * $perPage;

        while (($row = fgetcsv($handle, self::MAX_LINE)) !== false) {
            if ($row === [null] || count($row) !== count($header)) {
                continue;
            }
            if ($needle !== '' && ! str_contains(mb_strtolower(implode(' ', $row)), $needle)) {
                continue;
            }
            if ($total >= $skip && count($rows) < $perPage) {
                $rows[] = array_combine($header, array_map(fn ($v) => mb_substr((string) $v, 0, 120), $row));
            }
            $total++;
        }
        fclose($handle);

        $lastPage = max(1, (int) ceil($total / $perPage));

        return ['columns' => $header, 'rows' => $rows, 'total' => $total, 'page' => min($page, $lastPage), 'per_page' => $perPage, 'last_page' => $lastPage];
    }

    /** @return list<string> */
    private function readHeader($handle): array
    {
        $line = fgetcsv($handle, self::MAX_LINE);
        if ($line === false || $line === [null]) {
            throw new DatasetException('The file is empty.');
        }
        $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line[0]);

        return array_map(fn ($h) => trim((string) $h), $line);
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $sample
     * @return list<array{0: string, 1: string}>
     */
    private function schema(array $header, array $sample): array
    {
        $out = [];
        foreach ($header as $i => $name) {
            $value = trim((string) ($sample[$i] ?? ''));
            $type = match (true) {
                (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $value) => 'date',
                (bool) preg_match('/^-?\d+$/', $value) => 'integer',
                is_numeric($value) => 'decimal',
                default => 'string',
            };
            $out[] = [$name, $type];
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $monthly
     * @param  array<string, float>  $byDate
     * @param  array<int, array{name: string, units: float, revenue: float}>  $products
     * @return array<string, mixed>
     */
    private function summary(array $monthly, array $byDate, array $products, string $type, ?string $end): array
    {
        $months = [];
        foreach ($monthly as $key => $value) {
            $months[] = ['month' => $key, 'value' => round($value, 2)];
        }

        // Trend: the latest 28 days of data versus the 28 days before them.
        $trend = null;
        if ($end !== null) {
            $endTs = strtotime($end);
            $recent = $previous = 0.0;
            foreach ($byDate as $date => $value) {
                $age = (int) floor(($endTs - strtotime($date)) / 86400);
                if ($age < 28) {
                    $recent += $value;
                } elseif ($age < 56) {
                    $previous += $value;
                }
            }
            $trend = $previous > 0 ? round(($recent - $previous) / $previous * 100, 1) : null;
        }

        uasort($products, fn ($x, $y) => $y['units'] <=> $x['units']);
        $top = [];
        foreach (array_slice($products, 0, 5, true) as $id => $p) {
            $top[] = ['product_id' => $id, 'name' => $p['name'], 'units' => (int) round($p['units']), 'revenue' => round($p['revenue'], 2)];
        }

        return [
            'metric' => $type === 'sales_history' ? 'Revenue (₱)' : 'Units sold',
            'monthly' => array_slice($months, -12),
            'trend_percent' => $trend,
            'months_covered' => count($months),
            'products' => count($products),
            'top_products' => $top,
        ];
    }
}
