<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BusinessAnalytics\ProductAdvisorRepository;
use App\Services\ProductStrategy\ProductStrategyCatalogRepository;
use App\Services\ProductStrategy\ProductStrategyMasterPagination;
use App\Services\ProductStrategy\ProductStrategySpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ProductStrategyCatalogController extends Controller
{
    public function productFocusIndex(
        Request $request,
        ProductStrategyMasterPagination $pages,
        ProductAdvisorRepository $products,
    ): View {
        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 50);
        $rows = $pages->productFocus($search, $perPage)->withQueryString();
        $items = $products->searchItems($search, 'ALL', 50);

        return view('product-strategy.product-focus', compact('rows', 'items', 'search'));
    }

    public function productFocusStore(Request $request, ProductStrategyCatalogRepository $catalog): RedirectResponse
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:100'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'focus_category' => ['required', 'string', 'max:80'],
        ]);

        $itemName = trim((string) ($data['item_name'] ?? ''));
        if ($itemName === '') {
            $itemName = $catalog->itemName((string) $data['item_code']);
        }

        $catalog->saveProductFocus(
            (string) $data['item_code'],
            $itemName,
            (string) $data['focus_category'],
        );

        return back()->with('status', 'Product Focus berhasil disimpan.');
    }

    public function productFocusDelete(int $id, ProductStrategyCatalogRepository $catalog): RedirectResponse
    {
        $catalog->deleteProductFocus($id);

        return back()->with('status', 'Product Focus dihapus.');
    }

    public function productFocusImport(Request $request, ProductStrategySpreadsheetService $spreadsheet): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        try {
            $result = $spreadsheet->importProductFocus($request->file('file'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with(
            'status',
            "Import Product Focus selesai. Data lama sudah dihapus dan diganti isi Excel: {$result['processed']} baris masuk, {$result['skipped']} baris dilewati.",
        );
    }

    public function productFocusTemplate(ProductStrategySpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $path = $this->temporaryTemplatePath('product_focus_template.xlsx');
        $spreadsheet->productFocusTemplate($path);

        return response()->download($path, 'product_focus_template.xlsx')->deleteFileAfterSend(true);
    }

    public function productFocusExport(ProductStrategySpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $filename = 'product_focus_'.now()->format('Ymd_His').'.xlsx';
        $path = $this->temporaryTemplatePath($filename);
        $spreadsheet->exportProductFocus($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function similarItemsIndex(
        Request $request,
        ProductStrategyMasterPagination $pages,
        ProductAdvisorRepository $products,
    ): View {
        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 50);
        $rows = $pages->similarItems($search, $perPage)->withQueryString();
        $items = $products->searchItems($search, 'ALL', 50);

        return view('product-strategy.similar-items', compact('rows', 'items', 'search'));
    }

    public function similarItemsStore(Request $request, ProductStrategyCatalogRepository $catalog): RedirectResponse
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:100', 'different:similar_item_code'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'similar_item_code' => ['required', 'string', 'max:100'],
            'similar_item_name' => ['nullable', 'string', 'max:255'],
        ]);

        $itemName = trim((string) ($data['item_name'] ?? ''))
            ?: $catalog->itemName((string) $data['item_code']);
        $similarName = trim((string) ($data['similar_item_name'] ?? ''))
            ?: $catalog->itemName((string) $data['similar_item_code']);

        $catalog->saveSimilarItem(
            (string) $data['item_code'],
            $itemName,
            (string) $data['similar_item_code'],
            $similarName,
        );

        return back()->with('status', 'Relasi Similar Item berhasil disimpan.');
    }

    public function similarItemsDelete(int $id, ProductStrategyCatalogRepository $catalog): RedirectResponse
    {
        $catalog->deleteSimilarItem($id);

        return back()->with('status', 'Relasi Similar Item dihapus.');
    }

    public function similarItemsImport(Request $request, ProductStrategySpreadsheetService $spreadsheet): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        try {
            $result = $spreadsheet->importSimilarItems($request->file('file'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with(
            'status',
            "Import Similar Items selesai. Data lama sudah dihapus dan diganti isi Excel: {$result['processed']} relasi masuk, {$result['skipped']} baris dilewati.",
        );
    }

    public function similarItemsTemplate(ProductStrategySpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $path = $this->temporaryTemplatePath('similar_items_template.xlsx');
        $spreadsheet->similarItemsTemplate($path);

        return response()->download($path, 'similar_items_template.xlsx')->deleteFileAfterSend(true);
    }

    public function similarItemsExport(ProductStrategySpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $filename = 'similar_items_'.now()->format('Ymd_His').'.xlsx';
        $path = $this->temporaryTemplatePath($filename);
        $spreadsheet->exportSimilarItems($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function temporaryTemplatePath(string $filename): string
    {
        $directory = storage_path('app/tmp');
        File::ensureDirectoryExists($directory);

        return $directory.'/'.uniqid('strategy_', true).'_'.$filename;
    }
}
