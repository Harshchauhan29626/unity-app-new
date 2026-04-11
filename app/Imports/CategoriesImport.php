<?php

namespace App\Imports;

use App\Models\CircleCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class CategoriesImport
{
    public function import(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsvRows($file),
            'xlsx' => $this->readXlsxRows($file),
            default => throw new \RuntimeException('Unsupported file type. Please upload CSV or XLSX.'),
        };

        if ($rows === []) {
            return [
                'imported_count' => 0,
                'skipped_duplicate_count' => 0,
                'skipped_empty_count' => 0,
            ];
        }

        $existingNormalized = CircleCategory::query()
            ->where('level', 1)
            ->pluck('name')
            ->mapWithKeys(fn ($name) => [$this->normalizeName((string) $name) => true])
            ->all();

        $seenInFile = [];
        $importedCount = 0;
        $skippedDuplicateCount = 0;
        $skippedEmptyCount = 0;

        $now = now();
        $insertRows = [];

        foreach ($rows as $row) {
            $nameRaw = $this->firstNonNull($row, ['name', 'category', 'category_name']);
            $name = trim((string) ($nameRaw ?? ''));

            if ($name === '') {
                $skippedEmptyCount++;
                continue;
            }

            $normalizedName = $this->normalizeName($name);
            if (isset($existingNormalized[$normalizedName]) || isset($seenInFile[$normalizedName])) {
                $skippedDuplicateCount++;
                continue;
            }

            $slug = $this->nullableTrim($this->firstNonNull($row, ['slug']));
            $circleKey = $this->nullableTrim($this->firstNonNull($row, ['circle_key']));
            $sortOrder = $this->toInt($this->firstNonNull($row, ['sort_order']), 0);
            $isActive = $this->toBool($this->firstNonNull($row, ['is_active']), true);

            $insertRows[] = [
                'parent_id' => null,
                'name' => $name,
                'slug' => $slug ?: Str::slug($name),
                'circle_key' => $circleKey,
                'level' => 1,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $seenInFile[$normalizedName] = true;
            $existingNormalized[$normalizedName] = true;
        }

        if ($insertRows !== []) {
            foreach (array_chunk($insertRows, 500) as $chunk) {
                CircleCategory::query()->insert($chunk);
            }

            $importedCount = count($insertRows);
        }

        return [
            'imported_count' => $importedCount,
            'skipped_duplicate_count' => $skippedDuplicateCount,
            'skipped_empty_count' => $skippedEmptyCount,
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readCsvRows(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'r');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            fclose($stream);
            return [];
        }

        $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);

        $rows = [];

        while (($data = fgetcsv($stream)) !== false) {
            if ($data === [null] || $data === []) {
                $rows[] = [];
                continue;
            }

            $row = [];
            foreach ($normalizedHeaders as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $data[$index] ?? null;
            }

            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readXlsxRows(UploadedFile $file): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($file->getRealPath());

        if ($opened !== true) {
            throw new \RuntimeException('Unable to open XLSX file.');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            throw new \RuntimeException('Unable to read worksheet from XLSX file.');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $shared = @simplexml_load_string($sharedStringsXml);
            if ($shared !== false) {
                foreach ($shared->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                        continue;
                    }

                    $value = '';
                    if (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $value .= (string) ($run->t ?? '');
                        }
                    }

                    $sharedStrings[] = $value;
                }
            }
        }

        $sheet = @simplexml_load_string($sheetXml);
        $zip->close();

        if ($sheet === false || ! isset($sheet->sheetData)) {
            return [];
        }

        $matrix = [];

        foreach ($sheet->sheetData->row as $rowNode) {
            $rowValues = [];

            foreach ($rowNode->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colIndex = $this->columnIndexFromCellRef($ref);

                $type = (string) ($cell['t'] ?? '');
                $value = null;

                if ($type === 's') {
                    $sharedIndex = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }

                $rowValues[$colIndex] = $value;
            }

            if ($rowValues !== []) {
                ksort($rowValues);
                $matrix[] = $rowValues;
            }
        }

        if ($matrix === []) {
            return [];
        }

        $headerRow = array_shift($matrix);
        $headerMap = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if ($normalized !== '') {
                $headerMap[$index] = $normalized;
            }
        }

        $rows = [];

        foreach ($matrix as $rowValues) {
            $row = [];
            foreach ($headerMap as $index => $header) {
                $row[$header] = $rowValues[$index] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        $header = strtolower(trim($header));
        $header = preg_replace('/\s+/', '_', $header) ?? '';

        return match ($header) {
            'category' => 'category',
            'category_name' => 'category_name',
            'name' => 'name',
            'slug' => 'slug',
            'circle_key' => 'circle_key',
            'sort_order' => 'sort_order',
            'is_active' => 'is_active',
            'id' => 'id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            default => $header,
        };
    }

    private function normalizeName(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed === '' ? null : $trimmed;
    }

    private function firstNonNull(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                return $row[$key];
            }
        }

        return null;
    }

    private function toInt(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    private function columnIndexFromCellRef(string $ref): int
    {
        if ($ref === '') {
            return 0;
        }

        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?? 'A';
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }
}
