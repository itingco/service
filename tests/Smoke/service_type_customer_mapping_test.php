<?php

declare(strict_types=1);

/** Static safety contract checks. These checks do NOT need a database connection. */
$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root.'/'.$path);
    if ($content === false) throw new RuntimeException('Missing '.$path);
    return $content;
};
$gateway = $read('app/Services/ServiceQuotations/ServiceQuotationErpGateway.php');
$mapping = $read('app/Services/ServiceQuotations/ServiceTypeCustomerRepository.php');
$migration = $read('database/migrations/2026_10_22_001300_create_service_type_customers.php');
$controller = $read('app/Http/Controllers/ServiceQuotationController.php');
$routes = $read('routes/service_quotations.php');
$show = $read('resources/views/service-quotations/show.blade.php');
$expected = [
    [str_contains($gateway, "'CustomerName' => (string) \$header->customer_name"), 'CustomerName from quotation'],
    [str_contains($gateway, 'customerIdForService($source, $serviceType)'), 'CustomerID from source + type mapping'],
    [!str_contains($gateway, 'private function customerId('), 'No legacy customer-name auto match'],
    [str_contains($mapping, "DB::connection('sqlsrv_apphub')"), 'Mapping DB_AppHub only'],
    [str_contains($mapping, "'INGCO', 'SMI'"), 'ERP source restricted'],
    [str_contains($mapping, "'dbo.AR_Customers'"), 'AR_Customers read only'],
    [!str_contains($mapping, '->insert(') && !str_contains($mapping, '->update(') && !str_contains($mapping, '->delete('), 'No ERP write in mapping repository'],
    [str_contains($migration, "protected \$connection = 'sqlsrv_apphub'"), 'Migration on DB_AppHub'],
    [str_contains($migration, 'uq_service_type_customers_source_type'), 'Unique mapping per source + type'],
    [str_contains($controller, 'saveServiceCustomer('), 'Mapping controller added'],
    [str_contains($routes, "'/service-customers'"), 'Master route available'],
    [!str_contains($show, 'name="erp_customer_id"'), 'Manual ERP CustomerID removed'],
];
foreach ($expected as [$ok, $name]) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$name}\n"); exit(1); }
    echo "PASS: {$name}\n";
}
