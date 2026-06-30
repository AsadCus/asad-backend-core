<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveBalanceImportService
{
    /** @var array<int, string> */
    private const HEADER = ['employee_no', 'leave_type_code', 'year', 'allocated', 'note'];

    /**
     * Import leave balances from an .xlsx file (header: employee_no, leave_type_code, year, allocated, note).
     * Upserts by (employee_id, leave_type_id, year). Bad rows are reported; good rows still import.
     *
     * @return array{imported:int, skipped:int, errors:array<int,string>}
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        if ($rows === []) {
            abort(422, 'The file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows));

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $line = 1; // header is line 1

        foreach ($rows as $cols) {
            $line++;
            $row = array_combine($header, array_pad($cols, count($header), null));

            $empNo = trim((string) ($row['employee_no'] ?? ''));
            $typeCode = trim((string) ($row['leave_type_code'] ?? ''));
            $year = trim((string) ($row['year'] ?? ''));
            $allocated = trim((string) ($row['allocated'] ?? ''));

            // Blank trailing rows are silently skipped.
            if ($empNo === '' && $typeCode === '' && $year === '' && $allocated === '') {
                $skipped++;

                continue;
            }

            $employee = Employee::query()->where('employee_no', $empNo)->first();
            if (! $employee) {
                $errors[] = "Line {$line}: unknown employee_no '{$empNo}'";

                continue;
            }

            $leaveType = LeaveType::query()->where('code', $typeCode)->first();
            if (! $leaveType) {
                $errors[] = "Line {$line}: unknown leave_type_code '{$typeCode}'";

                continue;
            }

            if (! ctype_digit($year) || (int) $year < 2000 || (int) $year > 2100) {
                $errors[] = "Line {$line}: invalid year '{$year}'";

                continue;
            }

            if (! is_numeric($allocated) || (float) $allocated < 0) {
                $errors[] = "Line {$line}: invalid allocated '{$allocated}'";

                continue;
            }

            LeaveBalance::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => (int) $year],
                ['allocated' => (float) $allocated, 'note' => trim((string) ($row['note'] ?? '')) ?: null],
            );
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** A blank .xlsx template with the expected header row and one example. */
    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            self::HEADER,
            ['EMP-0001', 'ANNUAL', (int) date('Y'), 12, 'Annual allocation'],
        ]);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'leave-balance-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Read the first worksheet into a zero-indexed array of rows (raw cell values, formulas calculated).
     *
     * @return array<int, array<int, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            abort(422, 'Could not read the uploaded spreadsheet.');
        }

        return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
    }
}
