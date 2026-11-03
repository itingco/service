<?php

declare(strict_types=1);

/** Standalone test: no framework / SQL Server connection required. */
namespace Illuminate\Support\Facades {
    final class FakeDocTypeConnection
    {
        public function __construct(private readonly string $source) {}
        public function select(string $sql, array $bindings = []): array
        {
            DB::$calls[] = ['source' => $this->source, 'sql' => $sql, 'bindings' => $bindings];
            if ($sql !== 'EXEC dbo.USP_HRM_DocTypes_QueryAll' || $bindings !== []) {
                throw new \RuntimeException('Procedure must be called WITHOUT ANY PARAMETER.');
            }
            return match ($this->source) {
                'sqlsrv_ingco' => [
                    (object) ['TableName' => 'otherType', 'DocTypeID' => '999', 'DocTypeCode' => 'WRONG', 'DocTypeName' => 'Not Service'],
                    (object) ['TableName' => ' serviceType ', 'DocTypeID' => '10', 'DocTypeCode' => 'SV-ING', 'DocTypeName' => 'Service INGCO'],
                    (object) ['DocTypeID' => '888', 'DocTypeCode' => 'NO-TABLE', 'DocTypeName' => 'Unknown'],
                ],
                'sqlsrv_smi' => [
                    (object) ['table_name' => 'SERVICETYPE', 'Type_ID' => '20', 'Type_Code' => 'SV-SMI', 'Type_Name' => 'Service SMI'],
                ],
                default => throw new \RuntimeException('Invalid data source'),
            };
        }
    }
    final class DB
    {
        public static array $calls = [];
        public static function connection(string $name): FakeDocTypeConnection
        {
            return new FakeDocTypeConnection($name);
        }
    }
    final class Log
    {
        public static function warning(string $message, array $context): void
        {
            throw new \RuntimeException('Unexpected log warning: '.$message);
        }
    }
}

namespace {
    function config(string $key, mixed $default = null): mixed
    {
        return $default;
    }
    function report(\Throwable $exception): void
    {
        throw $exception;
    }

    require_once dirname(__DIR__, 2).'/app/Services/ServiceQuotations/ServiceDocTypeRepository.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException('FAIL: '.$message);
    };
    $repo = new \App\Services\ServiceQuotations\ServiceDocTypeRepository();
    $ingco = $repo->all('INGCO');
    $smi = $repo->all('SMI');
    $assert(count($ingco) === 1 && $ingco[0]['code'] === 'SV-ING', 'INGCO manually filtered');
    $assert(count($smi) === 1 && $smi[0]['code'] === 'SV-SMI', 'SMI manually filtered');
    $assert($repo->find('10', 'INGCO')['code'] === 'SV-ING', 'Save validation follows INGCO database');
    $assert($repo->find('20', 'SMI')['code'] === 'SV-SMI', 'Save validation follows SMI database');
    try {
        $repo->find('20', 'INGCO');
        throw new \RuntimeException('FAIL: must reject cross-database service type');
    } catch (\RuntimeException $e) {
        $assert(str_contains($e->getMessage(), 'tidak tersedia'), 'Cross-database type rejected');
    }
    foreach (\Illuminate\Support\Facades\DB::$calls as $call) {
        $assert($call['sql'] === 'EXEC dbo.USP_HRM_DocTypes_QueryAll', 'Stored procedure has no parameter');
        $assert($call['bindings'] === [], 'No bindings');
    }
    $assert(count(\Illuminate\Support\Facades\DB::$calls) === 5, 'Expected 5 read-only calls');
    echo "PASS: Service Type per ERP, EXEC without parameters, manual TableName filter, save validation.\n";
}
