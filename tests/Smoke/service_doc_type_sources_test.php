<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/app/Services/ServiceQuotations/ServiceDocTypeRepository.php';

use App\Services\ServiceQuotations\ServiceDocTypeRepository as Types;

$expect = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAILED: '.$message);
};

$expect(Types::sourceConnection('INGCO') === 'sqlsrv_ingco', 'INGCO source mapping');
$expect(Types::sourceConnection('SMI') === 'sqlsrv_smi', 'SMI source mapping');
try {
    Types::sourceConnection('apphub');
    throw new RuntimeException('FAILED: source apphub must be rejected');
} catch (InvalidArgumentException) {
    // correct
}
$rows = [
    (object) ['TableName' => 'serviceType', 'DocTypeID' => '10', 'DocTypeCode' => 'SC-A', 'DocTypeName' => 'Service A'],
    (object) ['TableName' => 'anotherType', 'DocTypeID' => '99', 'DocTypeCode' => 'WRONG', 'DocTypeName' => 'Not Service'],
    (object) ['TableName' => 'SERVICE_TYPE', 'Type_ID' => '20', 'Type_Code' => 'SC-B', 'Type_Name' => 'Service B'],
];
$found = Types::parseRows($rows, 'serviceType', false);
$expect(count($found) === 2, 'unfiltered response must reject non-service types');
$expect($found[0]['code'] === 'SC-A' && $found[1]['code'] === 'SC-B', 'aliases / source type parsing');
$expect(Types::parseRows([(object) ['DocTypeID'=>'7','DocTypeCode'=>'SC-X','DocTypeName'=>'Type X']], 'serviceType', false) === [], 'unfiltered data without table identity must fail closed');
$expect(count(Types::parseRows([(object) ['ServiceTypeID'=>'2','ServiceTypeCode'=>'SC-X','ServiceTypeName'=>'Type X']], 'serviceType', true)) === 1, 'parameter-filtered result may omit TableName');
$expect(Types::parseRows([(object) ['TableName'=>'different','DocTypeID'=>'4','DocTypeCode'=>'FAIL']], 'serviceType', true) === [], 'explicit wrong table must be rejected');
$expect(count(Types::parseRows([(object) ['Type_Code'=>'SC-NOID','Type_Name'=>'No numeric id']], 'serviceType', true)) === 1, 'code-only identifier permitted when USP filters table');
$expect(Types::parseRows([], 'serviceType', true) === [], 'empty SP result');
echo "PASS: per-ERP Service Type mapping, parser, filtering and validation.\n";
