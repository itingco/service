<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SalesDataSource;
use App\Models\DashboardUser;
use App\Support\SalesDashboardRowNormalizer;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class CustomerAnalysisService
{
    public function __construct(
        private readonly SalesDataSource $salesDataSource,
        private readonly SalesDashboardRowNormalizer $normalizer,
    ) {
    }

    /**
     * @param array{database:string,status:string,search:string,sort:string,end_date:DateTimeImmutable,sales_person_id:?int,page:int} $filters
     * @return array<string,mixed>
     */
    public function overview(array $filters, DashboardUser $user): array
    {
        $effectiveSalesPersonId = $this->effectiveSalesPersonId($user, $filters['sales_person_id']);
        $sources = $this->sources($filters['database']);
        $endDate = $filters['end_date'];
        $startDate = $this->periodStart($endDate);

        $customers = [];
        foreach ($sources as $source) {
            foreach ($this->loadCustomers($source, $effectiveSalesPersonId) as $customer) {
                $customers[$customer['key']] = $customer + [
                    'status' => 'inactive',
                    'total_sales_3m' => 0.0,
                    'total_qty_3m' => 0.0,
                    'invoice_count_3m' => 0,
                    'last_purchase_date' => null,
                    'last_invoice' => null,
                    'last_purchase_items' => [],
                    'history_found' => false,
                ];
            }
        }

        $recent = $this->aggregateRecentTransactions($sources, $startDate, $endDate, array_keys($customers));
        foreach ($recent as $key => $metrics) {
            if (!isset($customers[$key])) {
                continue;
            }

            $customers[$key]['status'] = 'active';
            $customers[$key]['total_sales_3m'] = $metrics['sales'];
            $customers[$key]['total_qty_3m'] = $metrics['qty'];
            $customers[$key]['invoice_count_3m'] = count($metrics['invoices']);
            $customers[$key]['last_purchase_date'] = $metrics['last_date'];
            $customers[$key]['last_invoice'] = $metrics['last_invoice'];
        }

        $allRows = array_values($customers);
        $summary = [
            'total_customers' => count($allRows),
            'active_customers' => count(array_filter($allRows, static fn (array $row): bool => $row['status'] === 'active')),
            'inactive_customers' => count(array_filter($allRows, static fn (array $row): bool => $row['status'] === 'inactive')),
            'sales_3m' => array_sum(array_map(static fn (array $row): float => (float) $row['total_sales_3m'], $allRows)),
        ];

        $filtered = array_values(array_filter($allRows, function (array $row) use ($filters): bool {
            if ($filters['status'] !== 'all' && $row['status'] !== $filters['status']) {
                return false;
            }

            $search = trim($filters['search']);
            if ($search === '') {
                return true;
            }

            $haystack = implode(' ', [
                $row['customer_code'],
                $row['customer_name'],
                $row['sales_person_name'],
                $row['source'],
            ]);

            return stripos($haystack, $search) !== false;
        }));

        $this->sortCustomers($filtered, $filters['sort']);

        $perPage = max(10, (int) config('customer_analysis.per_page', 25));
        $totalFiltered = count($filtered);
        $lastPage = max(1, (int) ceil($totalFiltered / $perPage));
        $page = min(max(1, $filters['page']), $lastPage);
        $pageRows = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        $inactiveBySource = [];
        foreach ($pageRows as $row) {
            if ($row['status'] === 'inactive' && $row['customer_code'] !== '') {
                $inactiveBySource[$row['source']][] = $row['customer_code'];
            }
        }

        $historyEnd = $startDate->modify('-1 day');
        $historyByKey = [];
        foreach ($inactiveBySource as $source => $codes) {
            foreach ($this->findLastPurchases((string) $source, array_values(array_unique($codes)), $historyEnd) as $code => $history) {
                $historyByKey[$this->customerKey((string) $source, (string) $code)] = $history;
            }
        }

        foreach ($pageRows as &$row) {
            if ($row['status'] !== 'inactive') {
                continue;
            }

            $history = $historyByKey[$row['key']] ?? null;
            if ($history !== null) {
                $row['last_purchase_date'] = $history['date'];
                $row['last_invoice'] = $history['invoice'];
                $row['last_purchase_items'] = $history['items'];
                $row['history_found'] = true;
            }
        }
        unset($row);

        return [
            'filters' => $filters + ['sales_person_id' => $effectiveSalesPersonId],
            'sales_locked' => $user->isSales(),
            'period_start' => $startDate,
            'period_end' => $endDate,
            'summary' => $summary,
            'rows' => $pageRows,
            'pagination' => [
                'page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $totalFiltered,
                'from' => $totalFiltered === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $totalFiltered),
            ],
            'history_months' => max(12, (int) config('customer_analysis.history_months', 60)),
        ];
    }

    /**
     * Menyiapkan seluruh data sesuai filter untuk export Excel tanpa pagination.
     *
     * @param array{database:string,status:string,search:string,sort:string,end_date:DateTimeImmutable,sales_person_id:?int,page:int} $filters
     * @return array<string,mixed>
     */
    public function exportData(array $filters, DashboardUser $user): array
    {
        $effectiveSalesPersonId = $this->effectiveSalesPersonId($user, $filters['sales_person_id']);
        $sources = $this->sources($filters['database']);
        $endDate = $filters['end_date'];
        $startDate = $this->periodStart($endDate);

        $customers = [];
        foreach ($sources as $source) {
            foreach ($this->loadCustomers($source, $effectiveSalesPersonId) as $customer) {
                $customers[$customer['key']] = $customer + [
                    'status' => 'inactive',
                    'total_sales_3m' => 0.0,
                    'total_qty_3m' => 0.0,
                    'invoice_count_3m' => 0,
                    'last_purchase_date' => null,
                    'last_invoice' => null,
                    'last_purchase_items' => [],
                    'history_found' => false,
                ];
            }
        }

        $recent = [];
        $rawRows = [];
        foreach ($sources as $source) {
            try {
                $sourceRows = \App\Support\CachedSalesQuery::fetch($this->salesDataSource, $source, $startDate, $endDate);
            } catch (Throwable $e) {
                throw new RuntimeException("Gagal mengambil transaksi customer {$source}: {$e->getMessage()}", 0, $e);
            }

            foreach ($sourceRows as $rawRow) {
                $row = $this->normalizer->normalize($rawRow, $source);
                $customerCode = strtoupper(trim((string) ($row['customer_code'] ?? '')));
                if ($customerCode === '') {
                    continue;
                }

                $key = $this->customerKey($source, $customerCode);
                if (!isset($customers[$key])) {
                    continue;
                }

                $recent[$key] ??= [
                    'sales' => 0.0,
                    'qty' => 0.0,
                    'invoices' => [],
                    'last_date' => null,
                    'last_invoice' => null,
                ];
                $recent[$key]['sales'] += (float) ($row['sales'] ?? 0.0);
                $recent[$key]['qty'] += (float) ($row['quantity'] ?? 0.0);
                $invoiceKey = ((string) ($row['invoice_number'] ?? '') ?: 'NO-INVOICE').'|'.((string) ($row['invoice_date'] ?? ''));
                $recent[$key]['invoices'][$invoiceKey] = true;

                if ($this->isLater(
                    (string) ($row['invoice_date'] ?? ''),
                    (string) ($row['invoice_number'] ?? ''),
                    $recent[$key]['last_date'],
                    $recent[$key]['last_invoice'],
                )) {
                    $recent[$key]['last_date'] = (string) ($row['invoice_date'] ?? '');
                    $recent[$key]['last_invoice'] = (string) ($row['invoice_number'] ?? '');
                }

                $master = $customers[$key];
                $rawRows[] = array_merge($row, [
                    'customer_id' => $master['customer_id'],
                    'customer_code' => $master['customer_code'],
                    'customer_name' => $master['customer_name'],
                    'sales_person_id' => $master['sales_person_id'],
                    'sales_person_name' => $master['sales_person_name'],
                    '_customer_key' => $key,
                ]);
            }
        }

        foreach ($recent as $key => $metrics) {
            if (!isset($customers[$key])) {
                continue;
            }

            $customers[$key]['status'] = 'active';
            $customers[$key]['total_sales_3m'] = $metrics['sales'];
            $customers[$key]['total_qty_3m'] = $metrics['qty'];
            $customers[$key]['invoice_count_3m'] = count($metrics['invoices']);
            $customers[$key]['last_purchase_date'] = $metrics['last_date'];
            $customers[$key]['last_invoice'] = $metrics['last_invoice'];
        }

        $filtered = array_values(array_filter(array_values($customers), function (array $row) use ($filters): bool {
            if ($filters['status'] !== 'all' && $row['status'] !== $filters['status']) {
                return false;
            }

            $search = trim($filters['search']);
            if ($search === '') {
                return true;
            }

            $haystack = implode(' ', [
                $row['customer_code'],
                $row['customer_name'],
                $row['sales_person_name'],
                $row['source'],
            ]);

            return stripos($haystack, $search) !== false;
        }));

        $this->sortCustomers($filtered, $filters['sort']);

        $inactiveBySource = [];
        foreach ($filtered as $row) {
            if ($row['status'] === 'inactive' && $row['customer_code'] !== '') {
                $inactiveBySource[$row['source']][] = $row['customer_code'];
            }
        }

        $historyEnd = $startDate->modify('-1 day');
        $historyByKey = [];
        foreach ($inactiveBySource as $source => $codes) {
            $uniqueCodes = array_values(array_unique($codes));
            foreach (array_chunk($uniqueCodes, 500) as $codeChunk) {
                foreach ($this->findLastPurchases((string) $source, $codeChunk, $historyEnd) as $code => $history) {
                    $historyByKey[$this->customerKey((string) $source, (string) $code)] = $history;
                }
            }
        }

        foreach ($filtered as &$row) {
            if ($row['status'] !== 'inactive') {
                continue;
            }

            $history = $historyByKey[$row['key']] ?? null;
            if ($history !== null) {
                $row['last_purchase_date'] = $history['date'];
                $row['last_invoice'] = $history['invoice'];
                $row['last_purchase_items'] = $history['items'];
                $row['history_found'] = true;
            }
        }
        unset($row);

        $allowedKeys = array_fill_keys(array_map(
            static fn (array $row): string => (string) $row['key'],
            $filtered,
        ), true);

        $rawRows = array_values(array_filter(
            $rawRows,
            static fn (array $row): bool => isset($allowedKeys[(string) ($row['_customer_key'] ?? '')]),
        ));

        foreach ($rawRows as &$row) {
            unset($row['_customer_key']);
        }
        unset($row);

        usort($rawRows, static function (array $a, array $b): int {
            return strcmp((string) ($b['invoice_date'] ?? ''), (string) ($a['invoice_date'] ?? ''))
                ?: strnatcasecmp((string) ($b['invoice_number'] ?? ''), (string) ($a['invoice_number'] ?? ''))
                ?: strnatcasecmp((string) ($a['customer_name'] ?? ''), (string) ($b['customer_name'] ?? ''))
                ?: strnatcasecmp((string) ($a['item_code'] ?? ''), (string) ($b['item_code'] ?? ''));
        });

        return [
            'filters' => $filters + ['sales_person_id' => $effectiveSalesPersonId],
            'period_start' => $startDate,
            'period_end' => $endDate,
            'customer_rows' => $filtered,
            'raw_rows' => $rawRows,
        ];
    }

    /** @return array<string,mixed> */
    public function detail(
        string $source,
        int $customerId,
        DateTimeImmutable $endDate,
        DashboardUser $user,
    ): array {
        $source = strtoupper(trim($source));
        if (!in_array($source, ['INGCO', 'SMI'], true)) {
            throw new RuntimeException('Sumber database customer tidak valid.');
        }

        $effectiveSalesPersonId = $this->effectiveSalesPersonId($user, null);
        $customer = $this->loadCustomerById($source, $customerId, $effectiveSalesPersonId);
        if ($customer === null) {
            throw new AuthorizationException('Customer tidak ditemukan atau bukan customer yang menjadi tanggung jawab Sales ini.');
        }

        $startDate = $this->periodStart($endDate);
        $recentRows = $customer['customer_code'] !== ''
            ? $this->fetchRowsForCodes($source, $startDate, $endDate, [$customer['customer_code']])
            : [];

        $isActive = $recentRows !== [];
        $totalSales = array_sum(array_map(static fn (array $row): float => (float) $row['sales'], $recentRows));
        $totalQty = array_sum(array_map(static fn (array $row): float => (float) $row['quantity'], $recentRows));
        $invoices = [];
        $lastDate = null;
        $lastInvoice = null;
        foreach ($recentRows as $row) {
            $invoiceKey = ($row['invoice_number'] ?: 'NO-INVOICE').'|'.$row['invoice_date'];
            $invoices[$invoiceKey] = true;
            if ($this->isLater($row['invoice_date'], $row['invoice_number'], $lastDate, $lastInvoice)) {
                $lastDate = $row['invoice_date'];
                $lastInvoice = $row['invoice_number'];
            }
        }

        $top = $this->aggregateTopItems($recentRows);
        $monthly = $this->monthlyHistory($recentRows, $startDate, $endDate);
        $lastPurchase = null;

        if (!$isActive && $customer['customer_code'] !== '') {
            $history = $this->findLastPurchases(
                $source,
                [$customer['customer_code']],
                $startDate->modify('-1 day'),
            );
            $lastPurchase = $history[strtoupper($customer['customer_code'])] ?? null;
            if ($lastPurchase !== null) {
                $lastDate = $lastPurchase['date'];
                $lastInvoice = $lastPurchase['invoice'];
            }
        }

        usort($recentRows, static function (array $a, array $b): int {
            return strcmp((string) $b['invoice_date'], (string) $a['invoice_date'])
                ?: strnatcasecmp((string) $b['invoice_number'], (string) $a['invoice_number'])
                ?: strnatcasecmp((string) $a['item_code'], (string) $b['item_code']);
        });

        return [
            'customer' => $customer,
            'status' => $isActive ? 'active' : 'inactive',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'total_sales_3m' => $totalSales,
            'total_qty_3m' => $totalQty,
            'invoice_count_3m' => count($invoices),
            'last_purchase_date' => $lastDate,
            'last_invoice' => $lastInvoice,
            'top_qty' => $top['qty'],
            'top_sales' => $top['sales'],
            'monthly' => $monthly,
            'transactions' => array_slice($recentRows, 0, 250),
            'last_purchase' => $lastPurchase,
            'history_months' => max(12, (int) config('customer_analysis.history_months', 60)),
        ];
    }

    private function effectiveSalesPersonId(DashboardUser $user, ?int $requested): ?int
    {
        if (!$user->isSales()) {
            return $requested !== null && $requested > 0 ? $requested : null;
        }

        $assigned = (int) ($user->sales_person_id ?? 0);
        if ($assigned <= 0) {
            throw new AuthorizationException(
                'Akun Sales ini belum dipetakan ke Sales Person ERP. Administrator harus mengisi field Sales Person pada menu Pengguna.'
            );
        }

        return $assigned;
    }

    /** @return list<string> */
    private function sources(string $database): array
    {
        return match (strtoupper(trim($database))) {
            'INGCO' => ['INGCO'],
            'SMI' => ['SMI'],
            default => ['INGCO', 'SMI'],
        };
    }

    private function periodStart(DateTimeImmutable $endDate): DateTimeImmutable
    {
        return $endDate->modify('first day of this month')->modify('-2 months');
    }

    /** @return list<array<string,mixed>> */
    private function loadCustomers(string $source, ?int $salesPersonId): array
    {
        $connection = $this->connection($source);
        $sql = <<<'SQL'
SELECT
    c.CustomerID,
    c.CustomerName,
    c.CustomerCode,
    c.SalesPersonID,
    sp.SalesPersonName
FROM AR_Customers c
LEFT JOIN AR_SalesPersons sp ON sp.SalesPersonID = c.SalesPersonID
SQL;
        $bindings = [];
        if ($salesPersonId !== null) {
            $sql .= "\nWHERE c.SalesPersonID = ?";
            $bindings[] = $salesPersonId;
        }
        $sql .= "\nORDER BY c.CustomerName, c.CustomerCode";

        try {
            $rows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            throw new RuntimeException("Gagal membaca master customer {$source}: {$e->getMessage()}", 0, $e);
        }

        $result = [];
        foreach ($rows as $row) {
            $customerCode = strtoupper(trim((string) ($row->CustomerCode ?? '')));
            $customerId = (int) ($row->CustomerID ?? 0);
            if ($customerId <= 0) {
                continue;
            }

            $keyCode = $customerCode !== '' ? $customerCode : 'ID-'.$customerId;
            $result[] = [
                'key' => $this->customerKey($source, $keyCode),
                'source' => $source,
                'customer_id' => $customerId,
                'customer_code' => $customerCode,
                'customer_name' => trim((string) ($row->CustomerName ?? '')) ?: $customerCode,
                'sales_person_id' => (int) ($row->SalesPersonID ?? 0),
                'sales_person_name' => trim((string) ($row->SalesPersonName ?? '')),
            ];
        }

        return $result;
    }

    /** @return array<string,mixed>|null */
    private function loadCustomerById(string $source, int $customerId, ?int $salesPersonId): ?array
    {
        $connection = $this->connection($source);
        $sql = <<<'SQL'
SELECT TOP 1
    c.CustomerID,
    c.CustomerName,
    c.CustomerCode,
    c.SalesPersonID,
    sp.SalesPersonName
FROM AR_Customers c
LEFT JOIN AR_SalesPersons sp ON sp.SalesPersonID = c.SalesPersonID
WHERE c.CustomerID = ?
SQL;
        $bindings = [$customerId];
        if ($salesPersonId !== null) {
            $sql .= "\nAND c.SalesPersonID = ?";
            $bindings[] = $salesPersonId;
        }

        try {
            $rows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            throw new RuntimeException("Gagal membaca detail customer {$source}: {$e->getMessage()}", 0, $e);
        }

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];
        $customerCode = strtoupper(trim((string) ($row->CustomerCode ?? '')));
        return [
            'key' => $this->customerKey($source, $customerCode !== '' ? $customerCode : 'ID-'.$customerId),
            'source' => $source,
            'customer_id' => $customerId,
            'customer_code' => $customerCode,
            'customer_name' => trim((string) ($row->CustomerName ?? '')) ?: $customerCode,
            'sales_person_id' => (int) ($row->SalesPersonID ?? 0),
            'sales_person_name' => trim((string) ($row->SalesPersonName ?? '')),
        ];
    }

    /**
     * @param list<string> $sources
     * @param list<string> $allowedKeys
     * @return array<string,array{sales:float,qty:float,invoices:array<string,bool>,last_date:?string,last_invoice:?string}>
     */
    private function aggregateRecentTransactions(
        array $sources,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        array $allowedKeys,
    ): array {
        $allowed = array_fill_keys($allowedKeys, true);
        $aggregates = [];

        foreach ($sources as $source) {
            try {
                $rawRows = \App\Support\CachedSalesQuery::fetch($this->salesDataSource, $source, $startDate, $endDate);
            } catch (Throwable $e) {
                throw new RuntimeException("Gagal mengambil transaksi customer {$source}: {$e->getMessage()}", 0, $e);
            }

            foreach ($rawRows as $rawRow) {
                $row = $this->normalizer->normalize($rawRow, $source);
                $customerCode = strtoupper(trim((string) ($row['customer_code'] ?? '')));
                if ($customerCode === '') {
                    continue;
                }

                $key = $this->customerKey($source, $customerCode);
                if (!isset($allowed[$key])) {
                    continue;
                }

                $aggregates[$key] ??= [
                    'sales' => 0.0,
                    'qty' => 0.0,
                    'invoices' => [],
                    'last_date' => null,
                    'last_invoice' => null,
                ];
                $aggregates[$key]['sales'] += (float) ($row['sales'] ?? 0.0);
                $aggregates[$key]['qty'] += (float) ($row['quantity'] ?? 0.0);
                $invoiceKey = ((string) ($row['invoice_number'] ?? '') ?: 'NO-INVOICE').'|'.((string) ($row['invoice_date'] ?? ''));
                $aggregates[$key]['invoices'][$invoiceKey] = true;

                if ($this->isLater(
                    (string) ($row['invoice_date'] ?? ''),
                    (string) ($row['invoice_number'] ?? ''),
                    $aggregates[$key]['last_date'],
                    $aggregates[$key]['last_invoice'],
                )) {
                    $aggregates[$key]['last_date'] = (string) ($row['invoice_date'] ?? '');
                    $aggregates[$key]['last_invoice'] = (string) ($row['invoice_number'] ?? '');
                }
            }
        }

        return $aggregates;
    }

    /** @param list<array<string,mixed>> $rows */
    private function sortCustomers(array &$rows, string $sort): void
    {
        usort($rows, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'sales_asc' => ((float) $a['total_sales_3m'] <=> (float) $b['total_sales_3m'])
                    ?: strnatcasecmp((string) $a['customer_name'], (string) $b['customer_name']),
                'name_asc' => strnatcasecmp((string) $a['customer_name'], (string) $b['customer_name']),
                'name_desc' => strnatcasecmp((string) $b['customer_name'], (string) $a['customer_name']),
                'recent_desc' => strcmp((string) ($b['last_purchase_date'] ?? ''), (string) ($a['last_purchase_date'] ?? ''))
                    ?: ((float) $b['total_sales_3m'] <=> (float) $a['total_sales_3m']),
                default => ((float) $b['total_sales_3m'] <=> (float) $a['total_sales_3m'])
                    ?: strnatcasecmp((string) $a['customer_name'], (string) $b['customer_name']),
            };
        });
    }

    /**
     * @param list<string> $customerCodes
     * @return array<string,array{date:string,invoice:string,items:list<array<string,mixed>>}>
     */
    private function findLastPurchases(string $source, array $customerCodes, DateTimeImmutable $historyEnd): array
    {
        $pending = [];
        foreach ($customerCodes as $code) {
            $code = strtoupper(trim($code));
            if ($code !== '') {
                $pending[$code] = true;
            }
        }

        if ($pending === []) {
            return [];
        }

        $historyMonths = max(12, (int) config('customer_analysis.history_months', 60));
        $chunkMonths = max(1, (int) config('customer_analysis.history_chunk_months', 12));
        $earliest = $historyEnd->modify('-'.$historyMonths.' months')->modify('+1 day');
        $cursorEnd = $historyEnd;
        $result = [];

        while ($pending !== [] && $cursorEnd >= $earliest) {
            $candidateStart = $cursorEnd->modify('-'.$chunkMonths.' months')->modify('+1 day');
            $cursorStart = $candidateStart < $earliest ? $earliest : $candidateStart;
            $rows = $this->fetchRowsForCodes($source, $cursorStart, $cursorEnd, array_keys($pending));

            if ($rows !== []) {
                $grouped = [];
                foreach ($rows as $row) {
                    $code = strtoupper(trim((string) ($row['customer_code'] ?? '')));
                    if ($code !== '' && isset($pending[$code])) {
                        $grouped[$code][] = $row;
                    }
                }

                foreach ($grouped as $code => $customerRows) {
                    $latestDate = null;
                    $latestInvoice = null;
                    foreach ($customerRows as $row) {
                        if ($this->isLater(
                            (string) ($row['invoice_date'] ?? ''),
                            (string) ($row['invoice_number'] ?? ''),
                            $latestDate,
                            $latestInvoice,
                        )) {
                            $latestDate = (string) ($row['invoice_date'] ?? '');
                            $latestInvoice = (string) ($row['invoice_number'] ?? '');
                        }
                    }

                    if ($latestDate === null) {
                        continue;
                    }

                    $invoiceRows = array_values(array_filter(
                        $customerRows,
                        static fn (array $row): bool =>
                            (string) ($row['invoice_date'] ?? '') === $latestDate
                            && (($latestInvoice ?? '') === '' || (string) ($row['invoice_number'] ?? '') === $latestInvoice)
                    ));
                    if ($invoiceRows === []) {
                        $invoiceRows = array_values(array_filter(
                            $customerRows,
                            static fn (array $row): bool => (string) ($row['invoice_date'] ?? '') === $latestDate
                        ));
                    }

                    $top = $this->aggregateTopItems($invoiceRows);
                    $result[$code] = [
                        'date' => $latestDate,
                        'invoice' => $latestInvoice ?? '',
                        'items' => $top['qty'],
                    ];
                    unset($pending[$code]);
                }
            }

            $cursorEnd = $cursorStart->modify('-1 day');
        }

        return $result;
    }

    /** @param list<string> $customerCodes @return list<array<string,mixed>> */
    private function fetchRowsForCodes(
        string $source,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        array $customerCodes,
    ): array {
        $customerCodes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            $customerCodes,
        ))));

        if ($customerCodes === []) {
            return [];
        }

        $connection = $this->connection($source);
        $placeholders = implode(',', array_fill(0, count($customerCodes), '?'));
        $sql = "SELECT * FROM dbo.fn_PenjualanLini(?, ?) WHERE CustomerCode IN ({$placeholders})";
        $bindings = [$startDate->format('Y-m-d'), $endDate->format('Y-m-d'), ...$customerCodes];

        try {
            $rawRows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Customer Analysis membutuhkan kolom CustomerCode pada dbo.fn_PenjualanLini {$source}. Detail: {$e->getMessage()}",
                0,
                $e,
            );
        }

        $rows = [];
        foreach ($rawRows as $rawRow) {
            $row = $this->normalizer->normalize($rawRow, $source);
            $code = strtoupper(trim((string) ($row['customer_code'] ?? '')));
            if ($code !== '' && in_array($code, $customerCodes, true)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @return array{qty:list<array<string,mixed>>,sales:list<array<string,mixed>>} */
    private function aggregateTopItems(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $itemCode = strtoupper(trim((string) ($row['item_code'] ?? '')));
            $itemName = trim((string) ($row['item_name'] ?? ''));
            if ($itemCode === '' && $itemName === '') {
                continue;
            }
            $key = $itemCode !== '' ? $itemCode : $itemName;
            $groups[$key] ??= [
                'item_code' => $itemCode,
                'item_name' => $itemName !== '' ? $itemName : $itemCode,
                'quantity' => 0.0,
                'sales' => 0.0,
            ];
            $groups[$key]['quantity'] += (float) ($row['quantity'] ?? 0.0);
            $groups[$key]['sales'] += (float) ($row['sales'] ?? 0.0);
        }

        $byQty = array_values($groups);
        usort($byQty, static fn (array $a, array $b): int =>
            ((float) $b['quantity'] <=> (float) $a['quantity'])
            ?: ((float) $b['sales'] <=> (float) $a['sales'])
        );

        $bySales = array_values($groups);
        usort($bySales, static fn (array $a, array $b): int =>
            ((float) $b['sales'] <=> (float) $a['sales'])
            ?: ((float) $b['quantity'] <=> (float) $a['quantity'])
        );

        return [
            'qty' => array_slice($byQty, 0, 3),
            'sales' => array_slice($bySales, 0, 3),
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array{month:string,sales:float,qty:float}> */
    private function monthlyHistory(array $rows, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $months = [];
        $cursor = $startDate->modify('first day of this month');
        $lastMonth = $endDate->format('Y-m');
        while ($cursor->format('Y-m') <= $lastMonth) {
            $months[$cursor->format('Y-m')] = ['month' => $cursor->format('Y-m'), 'sales' => 0.0, 'qty' => 0.0];
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($rows as $row) {
            $date = (string) ($row['invoice_date'] ?? '');
            if (strlen($date) < 7) {
                continue;
            }
            $month = substr($date, 0, 7);
            if (!isset($months[$month])) {
                continue;
            }
            $months[$month]['sales'] += (float) ($row['sales'] ?? 0.0);
            $months[$month]['qty'] += (float) ($row['quantity'] ?? 0.0);
        }

        return array_values($months);
    }

    private function isLater(string $date, string $invoice, ?string $currentDate, ?string $currentInvoice): bool
    {
        if ($date === '') {
            return false;
        }
        if ($currentDate === null || $date > $currentDate) {
            return true;
        }
        if ($date < $currentDate) {
            return false;
        }

        return strnatcasecmp($invoice, (string) $currentInvoice) > 0;
    }

    private function customerKey(string $source, string $customerCode): string
    {
        return strtoupper(trim($source)).'|'.strtoupper(trim($customerCode));
    }

    private function connection(string $source): string
    {
        $source = strtoupper(trim($source));
        $connection = (string) config('customer_analysis.connections.'.$source, '');
        if ($connection === '') {
            throw new RuntimeException("Koneksi Customer Analysis untuk {$source} belum dikonfigurasi.");
        }

        return $connection;
    }
}
