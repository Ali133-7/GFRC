<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Log;

class ReportExporter
{
    /**
     * Export report data to specified format
     */
    public function export(array $data, string $format, array $metadata = []): mixed
    {
        return match (strtolower($format)) {
            'json' => $this->exportToJson($data, $metadata),
            'excel', 'xlsx', 'csv' => $this->exportToExcel($data, $format, $metadata),
            'pdf' => $this->exportToPdf($data, $metadata),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * Export to JSON format
     */
    protected function exportToJson(array $data, array $metadata): array
    {
        return [
            'metadata' => array_merge([
                'exported_at' => now()->toIso8601String(),
                'format' => 'json',
            ], $metadata),
            'report' => $data['report'] ?? null,
            'data' => $data['data'] ?? [],
            'aggregations' => $data['aggregations'] ?? [],
            'pagination' => $data['pagination'] ?? null,
        ];
    }

    /**
     * Export to Excel format
     */
    protected function exportToExcel(array $data, string $format, array $metadata): array
    {
        // Try PHPSpreadsheet first (if available)
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class) && extension_loaded('gd') && extension_loaded('zip')) {
            return $this->exportToExcelSpreadsheet($data, $format, $metadata);
        }
        
        // Fallback to simple CSV export
        return $this->exportToCsv($data, $metadata);
    }
    
    /**
     * Export to Excel using PHPSpreadsheet (requires gd and zip extensions)
     */
    protected function exportToExcelSpreadsheet(array $data, string $format, array $metadata): array
    {

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set metadata
        $spreadsheet->getProperties()
            ->setCreator($metadata['generated_by'] ?? 'System')
            ->setTitle($metadata['report_name'] ?? 'Report')
            ->setSubject('Report Export')
            ->setDescription('Generated report export');

        $row = 1;
        $column = 'A';

        // Add report title
        $sheet->setCellValue("A{$row}", $metadata['report_name'] ?? 'Report');
        $sheet->mergeCells("A{$row}:Z{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $row++;

        // Add generation info
        $sheet->setCellValue("A{$row}", "Generated: " . now()->format('Y-m-d H:i:s'));
        $sheet->setCellValue("B{$row}", "By: " . ($metadata['generated_by'] ?? 'Unknown'));
        $row += 2;

        // Add headers
        if (!empty($data['data'])) {
            $headers = array_keys($data['data'][0]);
            $colIndex = 0;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $row, $header);
                $colIndex++;
            }
            $row++;

            // Style headers
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCCCCC');

            // Add data rows
            foreach ($data['data'] as $rowData) {
                $colIndex = 0;
                foreach ($headers as $header) {
                    $sheet->setCellValueByColumnAndRow($colIndex + 1, $row, $rowData[$header] ?? '');
                    $colIndex++;
                }
                $row++;
            }

            // Auto-size columns
            foreach (range(1, count($headers)) as $colIndex) {
                $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex))
                    ->setAutoSize(true);
            }
        }

        // Add aggregations if available
        if (!empty($data['aggregations'])) {
            $row += 2;
            $sheet->setCellValue("A{$row}", 'Aggregations');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
            $row++;

            foreach ($data['aggregations'] as $agg) {
                $sheet->setCellValue("A{$row}", $agg['alias'] ?? $agg['field']);
                $sheet->setCellValue("B{$row}", $agg['value']);
                $row++;
            }
        }

        // Save to temporary file
        $filename = $this->generateFilename($metadata['report_name'] ?? 'report', strtolower($format));
        $tempPath = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        if ($format === 'csv') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
        } else {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        }

        $writer->save($tempPath);

        return [
            'download_url' => url("/api/v1/reports/download/" . basename($tempPath)),
            'filename' => $filename,
            'format' => $format,
            'size' => filesize($tempPath),
        ];
    }

    /**
     * Export to CSV format (fallback when PHPSpreadsheet is not available)
     */
    protected function exportToCsv(array $data, array $metadata): array
    {
        $filename = $this->generateFilename($metadata['report_name'] ?? 'report', 'csv');
        $tempPath = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $handle = fopen($tempPath, 'w');
        
        // Add metadata header
        fputcsv($handle, ['Report', $metadata['report_name'] ?? 'Report']);
        fputcsv($handle, ['Generated', $metadata['generated_at'] ?? now()]);
        fputcsv($handle, ['By', $metadata['generated_by'] ?? 'System']);
        fputcsv($handle, []); // Empty row
        
        // Add data headers
        if (!empty($data['data'])) {
            fputcsv($handle, array_keys($data['data'][0]));
            
            // Add data rows
            foreach ($data['data'] as $row) {
                fputcsv($handle, $row);
            }
        }
        
        fclose($handle);

        return [
            'download_url' => url("/api/v1/reports/download/" . basename($tempPath)),
            'filename' => $filename,
            'format' => 'csv',
            'size' => filesize($tempPath),
        ];
    }

    /**
     * Export to PDF format
     */
    protected function exportToPdf(array $data, array $metadata): array
    {
        // Fallback: Return HTML that can be printed as PDF
        $html = view('reports.exports.pdf', [
            'report' => $data['report'] ?? null,
            'data' => $data['data'] ?? [],
            'aggregations' => $data['aggregations'] ?? [],
            'metadata' => $metadata,
            'generated_at' => now(),
        ])->render();

        // If DomPDF is not available, return HTML with print instructions
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return [
                'html' => $html,
                'format' => 'html',
                'message' => 'PDF generation requires DomPDF. Install via: composer require barryvdh/laravel-dompdf',
                'print_instructions' => 'Use browser print (Ctrl+P) to save as PDF',
            ];
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = $this->generateFilename($metadata['report_name'] ?? 'report', 'pdf');
        $tempPath = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents($tempPath, $dompdf->output());

        return [
            'download_url' => url("/api/v1/reports/download/" . basename($tempPath)),
            'filename' => $filename,
            'format' => 'pdf',
            'size' => filesize($tempPath),
        ];
    }

    /**
     * Generate unique filename
     */
    protected function generateFilename(string $reportName, string $extension): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $reportName);
        $timestamp = now()->format('Ymd_His');
        $random = substr(md5(uniqid()), 0, 6);
        
        return "{$safeName}_{$timestamp}_{$random}.{$extension}";
    }
}
