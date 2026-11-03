<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsureServiceQuotationCanSend
{
    public function handle(Request $request, Closure $next): Response
    {
        $db = DB::connection('sqlsrv_apphub');
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db_name')->db_name ?? ''), 'DB_AppHub') !== 0) {
            abort(503, 'Koneksi database penawaran bukan DB_AppHub.');
        }
        $quote = $db->table('service_quotations')->where('id', (int) $request->route('id'))->first();
        if (!$quote) abort(404);
        if (($quote->approval_status ?? 'pending') === 'cancel') {
            abort(409, 'Penawaran berstatus Cancel tidak dapat dikirim ke ERP.');
        }
        return $next($request);
    }
}
