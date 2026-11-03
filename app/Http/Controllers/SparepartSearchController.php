<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Spareparts\SparepartSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SparepartSearchController extends Controller
{
    public function index(Request $request, SparepartSearchService $service): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100', 'regex:~^[a-zA-Z0-9._()/ -]+$~D'],
            'database' => ['nullable', 'in:all,ingco,smi'],
        ], [
            'q.regex' => 'Kode boleh berisi huruf, angka, titik, garis bawah, spasi, tanda minus, garis miring dan tanda kurung.',
        ]);

        $code = strtoupper(trim((string) ($validated['q'] ?? '')));
        $database = (string) ($validated['database'] ?? 'all');
        $result = $code !== '' ? $service->search($code, $database) : null;

        return view('spareparts.search', compact('code', 'database', 'result'));
    }
}
