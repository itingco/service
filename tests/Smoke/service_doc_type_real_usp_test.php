<?php

declare(strict_types=1);

/** Standalone regression test for the actual USP result columns from SSMS screenshot. */
namespace Illuminate\Support\Facades {
    final class FakeServiceTypeConnection
    {
        public function __construct(private readonly string $source) {}
        public function select(string $sql, array $bindings = []): array
        {
            DB::$calls[] = ['source' => $this->source, 'sql' => $sql, 'bindings' => $bindings];
            if ($sql !== 'EXEC dbo.USP_HRM_DocTypes_QueryAll' || $bindings !== []) {
                throw new \RuntimeException('USP must be called without parameters.');
            }
            if ($this->source === 'sqlsrv_smi') {
                return [
                    (object) ['TableName' => 'ServiceType', 'Type' => 'SMI-SV', 'Description' => 'Service SMI', 'RecordID' => 77],
                    (object) ['TableName' => 'EmployeeContract', 'Type' => 'SMI', 'Description' => 'Not service', 'RecordID' => 4],
                ];
            }
            if ($this->source !== 'sqlsrv_ingco') throw new \RuntimeException('Unknown DB source');
            return [
                (object) ['TableName' => 'EmployeeContract', 'Type' => 'ITI', 'Description' => 'INGCO TEKNIKA INDONESIA', 'RecordID' => 5],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-ACH', 'Description' => 'Service Center Aceh', 'RecordID' => 14],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-BDG', 'Description' => 'Service Center Bandung', 'RecordID' => 20],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-JKT', 'Description' => 'Service Center Jakarta', 'RecordID' => 15],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-JND', 'Description' => 'Service Center Juanda', 'RecordID' => 11],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-KLS', 'Description' => 'Service Kiels Raja', 'RecordID' => 31],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-LNG', 'Description' => 'Service Point Langgeng', 'RecordID' => 30],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-LTC', 'Description' => 'Service Center LTC', 'RecordID' => 23],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-PKU', 'Description' => 'Service Center Pekanbaru', 'RecordID' => 12],
                (object) ['TableName' => 'ServiceType', 'Type' => 'SC-SB', 'Description' => 'Service Center Surabaya', 'RecordID' => 13],
                (object) ['TableName' => 'Termination', 'Type' => 'INC', 'Description' => 'INGCO', 'RecordID' => 9],
            ];
        }
    }

    final class DB
    {
        public static array $calls = [];
        public static function connection(string $name): FakeServiceTypeConnection
        {
            return new FakeServiceTypeConnection($name);
        }
    }

    final class Log
    {
        public static function warning(string $message, array $context): void
        {
            throw new \RuntimeException('Unexpected parser warning: '.$message);
        }
    }
}

namespace {
    function config(string $key, mixed $default = null): mixed { return $default; }
    function report(\Throwable $e): void { throw $e; }

    require_once dirname(__DIR__, 2).'/app/Services/ServiceQuotations/ServiceDocTypeRepository.php';

    $test = static function (bool $condition, string $name): void {
        if (!$condition) throw new \RuntimeException('FAIL: '.$name);
    };
    $repo = new \App\Services\ServiceQuotations\ServiceDocTypeRepository();
    $ingco = $repo->all('INGCO');
    $test(count($ingco) === 9, 'Nine INGCO ServiceType rows recognized');
    $test(count(array_filter($ingco, static fn (array $x): bool => $x['code'] === 'ITI')) === 0, 'EmployeeContract excluded');
    $ach = $repo->find('14', 'INGCO');
    $test($ach === ['id' => '14', 'code' => 'SC-ACH', 'label' => 'Service Center Aceh'], 'Screenshot actual columns mapped');
    $test($repo->find('13', 'INGCO')['code'] === 'SC-SB', 'ID 13 points to Surabaya');
    $smi = $repo->all('SMI');
    $test(count($smi) === 1 && $smi[0]['id'] === '77' && $smi[0]['code'] === 'SMI-SV', 'SMI source independent');
    try {
        $repo->find('77', 'INGCO');
        throw new \RuntimeException('FAIL: foreign database doc type accepted');
    } catch (\RuntimeException $e) {
        $test(str_contains($e->getMessage(), 'tidak tersedia'), 'Reject foreign database doc type');
    }
    $test(count(\Illuminate\Support\Facades\DB::$calls) === 5, 'Read-only calls executed');
    foreach (\Illuminate\Support\Facades\DB::$calls as $call) {
        $test($call['sql'] === 'EXEC dbo.USP_HRM_DocTypes_QueryAll' && $call['bindings'] === [], 'EXEC without parameters');
    }
    $extra = \App\Services\ServiceQuotations\ServiceDocTypeRepository::parseRows([
        (object) ['TableName' => ' sErViCeTyPe ', 'Type' => 'SC-X', 'Description' => 'Example', 'RecordID' => 81],
        (object) ['TableName' => 'OtherType', 'Type' => 'BAD', 'Description' => 'Wrong', 'RecordID' => 82],
    ]);
    $test(count($extra) === 1 && $extra[0]['id'] === '81', 'TableName filtered case-insensitive');
    $legacy = \App\Services\ServiceQuotations\ServiceDocTypeRepository::parseRows([
        (object) ['TableName' => 'serviceType', 'DocTypeID' => '101', 'DocTypeCode' => 'A', 'DocTypeName' => 'Legacy'],
    ]);
    $test(count($legacy) === 1 && $legacy[0]['code'] === 'A', 'Legacy alias backwards compatibility');
    echo "PASS: Nine INGCO ServiceTypes, RecordID/Type/Description mapping, SMI isolation, filter, EXEC without params.\n";
}
