<?php

declare(strict_types=1);

namespace App\Services\ProductStrategy;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ProductStrategySpreadsheetService
{
    public function __construct(private readonly ProductStrategyCatalogRepository $repository)
    {
    }

    /** @return array{processed:int,skipped:int} */
    public function importProductFocus(UploadedFile $file): array
    {
        $rows = $this->rows($file);
        $normalized = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $itemCode = strtoupper($this->value($row, ['item_code', 'itemcode', 'kode_item', 'kodeitem', 'kode_barang', 'kodebarang']));
            $itemName = $this->value($row, ['item_name', 'itemname', 'nama_item', 'namaitem', 'nama_barang', 'namabarang']);
            $focus = strtoupper($this->value($row, ['product_focus', 'productfocus', 'focus_category', 'focuscategory', 'category', 'kategori']));

            if ($itemCode === '' || $focus === '') {
                $skipped++;
                continue;
            }

            if (isset($normalized[$itemCode])) {
                $skipped++;
            }

            // Jika ItemCode duplicate, baris terakhir tetap menjadi sumber data.
            $normalized[$itemCode] = [
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'focus_category' => $focus,
            ];
        }

        if ($normalized === []) {
            throw new RuntimeException('Excel Product Focus tidak memiliki baris valid. Data existing tidak dihapus.');
        }

        $processed = $this->repository->replaceProductFocus(array_values($normalized));

        return compact('processed', 'skipped');
    }

    /** @return array{processed:int,skipped:int} */
    public function importSimilarItems(UploadedFile $file): array
    {
        $rows = $this->rows($file);
        $normalized = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $itemCode = strtoupper($this->value($row, ['item_code', 'itemcode', 'kode_item', 'kodeitem']));
            $similarItemCode = strtoupper($this->value($row, ['similar_item_code', 'similaritemcode', 'similar_item', 'similaritem', 'item_similar']));

            if ($itemCode === '' || $similarItemCode === '' || $itemCode === $similarItemCode) {
                $skipped++;
                continue;
            }

            $key = $itemCode.'|'.$similarItemCode;
            if (isset($normalized[$key])) {
                $skipped++;
                continue;
            }

            $normalized[$key] = [
                'item_code' => $itemCode,
                'similar_item_code' => $similarItemCode,
            ];
        }

        if ($normalized === []) {
            throw new RuntimeException('Excel Similar Items tidak memiliki baris valid. Data existing tidak dihapus.');
        }

        $processed = $this->repository->replaceSimilarItems(array_values($normalized));

        return compact('processed', 'skipped');
    }

    public function productFocusTemplate(string $path): void
    {
        $this->writeWorkbook(
            $path,
            ['item_code', 'item_name', 'product_focus'],
            [
                ['AG750282', 'Angle Grinder 750W', 'TOP 25'],
                ['ITEM002', 'Contoh Item 2', 'TOP 50'],
            ],
            'Product Focus',
        );
    }

    public function similarItemsTemplate(string $path): void
    {
        $this->writeWorkbook(
            $path,
            ['item_code', 'item_name', 'similar_item_code', 'similar_item_name'],
            [
                ['ITEM001', 'Gergaji A', 'ITEM003', 'Gergaji C'],
                ['ITEM001', 'Gergaji A', 'ITEM002', 'Gergaji B'],
            ],
            'Similar Items',
        );
    }

    public function exportProductFocus(string $path): void
    {
        $rows = array_map(
            static fn (array $row): array => [
                (string) ($row['item_code'] ?? ''),
                (string) ($row['item_name'] ?? ''),
                (string) ($row['focus_category'] ?? ''),
                ((int) ($row['focus_rank'] ?? 999999)) >= 999999 ? '' : (int) $row['focus_rank'],
                (string) ($row['updated_at'] ?? ''),
            ],
            $this->repository->productFocusRows(),
        );

        $this->writeWorkbook(
            $path,
            ['item_code', 'item_name', 'product_focus', 'priority', 'updated'],
            $rows,
            'Product Focus',
        );
    }

    public function exportSimilarItems(string $path): void
    {
        $rows = array_map(
            static fn (array $row): array => [
                (string) ($row['item_code'] ?? ''),
                (string) ($row['item_name'] ?? ''),
                (string) ($row['similar_item_code'] ?? ''),
                (string) ($row['similar_item_name'] ?? ''),
            ],
            $this->repository->similarItemRows(),
        );

        $this->writeWorkbook(
            $path,
            ['item_code', 'item_name', 'similar_item_code', 'similar_item_name'],
            $rows,
            'Similar Items',
        );
    }

    /** @return list<array<string,string>> */
    private function rows(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $exception) {
            throw new RuntimeException('File Excel tidak dapat dibaca. Gunakan XLSX, XLS, atau CSV yang valid.', 0, $exception);
        }

        $data = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (count($data) < 2) {
            return [];
        }

        $headers = array_map(fn (mixed $value): string => $this->header((string) $value), array_shift($data));
        $rows = [];

        foreach ($data as $values) {
            $row = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($values[$index] ?? ''));
                }
            }

            if (array_filter($row, static fn (string $value): bool => $value !== '') !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param array<string,string> $row @param list<string> $keys */
    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim($row[$key]) !== '') {
                return trim($row[$key]);
            }
        }

        return '';
    }

    private function header(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    private function writeWorkbook(string $path, array $headers, array $rows, string $sheetTitle): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->fromArray($headers, null, 'A1');

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $lastColumn = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.'1');

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save($path);
    }
}
