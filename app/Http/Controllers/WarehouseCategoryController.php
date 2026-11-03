<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WarehouseCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WarehouseCategoryController extends Controller
{
    public function index(WarehouseCategoryService $service): View
    {
        return view('warehouse-categories.index', [
            'categories' => $service->categories(),
            'members' => $service->members(),
            'warehouses' => $service->warehouses(),
        ]);
    }

    public function store(Request $request, WarehouseCategoryService $service): RedirectResponse
    {
        $data = $request->validate(['name' => ['required','string','max:100'], 'warehouses' => ['array'], 'warehouses.*' => ['regex:/^(INGCO|SMI)\|[1-9][0-9]*$/D']]);
        $service->create(trim($data['name']), $data['warehouses'] ?? []);
        return back()->with('success', 'Kategori gudang disimpan.');
    }

    public function update(Request $request, int $id, WarehouseCategoryService $service): RedirectResponse
    {
        $data = $request->validate(['name' => ['required','string','max:100'], 'warehouses' => ['array'], 'warehouses.*' => ['regex:/^(INGCO|SMI)\|[1-9][0-9]*$/D']]);
        $service->update($id, trim($data['name']), $data['warehouses'] ?? []);
        return back()->with('success', 'Kategori gudang diperbarui.');
    }

    public function destroy(int $id, WarehouseCategoryService $service): RedirectResponse
    {
        $service->delete($id);
        return back()->with('success', 'Kategori gudang dihapus.');
    }
}
