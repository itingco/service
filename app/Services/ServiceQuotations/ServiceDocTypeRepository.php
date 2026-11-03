<?php

declare(strict_types=1);

namespace App\Services\ServiceQuotations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Service Type MUST be read from the SAME ERP that owns the damaged item.
 * READ-ONLY calls. Never writes to AS_INGCO or AS_SMI.
 *
 * ERP contract: USP_HRM_DocTypes_QueryAll has NO PARAMETERS.
 * Its full result set must be filtered on the TableName field in Laravel,
 * so unrelated document types are never offered for service quotations.
 */
final class ServiceDocTypeRepository
{
    /** Restrict connections to the two approved ERP sources. */
    public static function sourceConnection(string $source): string
    {
        return match (strtoupper(trim($source))) {
            'INGCO' => 'sqlsrv_ingco',
            'SMI' => 'sqlsrv_smi',
            default => throw new InvalidArgumentException('Database barang rusak harus INGCO atau SMI.'),
        };
    }

    /** @return list<array{id:string,code:string,label:string}> */
    public function all(string $source): array
    {
        $source = strtoupper(trim($source));
        $connection = self::sourceConnection($source);
        $procedure = (string) config('service_quotations.doc_types_procedure', 'dbo.USP_HRM_DocTypes_QueryAll');
        if ($procedure !== 'dbo.USP_HRM_DocTypes_QueryAll') {
            throw new RuntimeException('Stored procedure service type belum dikenali.');
        }
        $tableName = (string) config('service_quotations.doc_types_table_name', 'serviceType');
        if ($tableName !== 'serviceType') {
            throw new RuntimeException('Filter service type harus serviceType.');
        }

        // The ERP procedure has NO parameters in either database.
        // Never try @TableName: it is not part of the stored procedure contract.
        try {
            $rows = DB::connection($connection)->select('EXEC dbo.USP_HRM_DocTypes_QueryAll');
        } catch (Throwable $e) {
            report($e);
            throw new RuntimeException(
                "Gagal membaca Service Type dari AS_{$source}. Periksa koneksi dan USP_HRM_DocTypes_QueryAll (tanpa parameter).",
                0,
                $e,
            );
        }

        // Filter TableName = serviceType AFTER EXEC, NOT in the EXEC call.
        // Pass false so rows without a TableName field are rejected (fail closed).
        $types = self::parseRows($rows, $tableName, false);
        if ($types === [] && $rows !== []) {
            // Log column names ONLY (never customer/ERP values), useful when
            // each ERP has a different stored-procedure result contract.
            Log::warning('Service quotation: unmapped service type USP result', [
                'source' => $source,
                'connection' => $connection,
                'row_count' => count($rows),
                'filter' => 'TableName = serviceType (in Laravel)',
                'columns' => array_keys((array) $rows[0]),
            ]);
        }

        return $types;
    }

    /**
     * @param list<object|array<string,mixed>> $rows
     * @return list<array{id:string,code:string,label:string}>
     * $filteredAtSource=true is retained for backwards-compatible parser tests;
     * the production all() method always passes false.
     */
    public static function parseRows(array $rows, string $tableName = 'serviceType', bool $filteredAtSource = false): array
    {
        $types = [];
        foreach ($rows as $row) {
            $raw = [];
            foreach ((array) $row as $key => $value) {
                $field = self::normalizedKey((string) $key);
                if ($field !== '' && !isset($raw[$field])) {
                    $raw[$field] = is_scalar($value) ? trim((string) $value) : '';
                }
            }
            $read = static function (array $names) use ($raw): string {
                foreach ($names as $name) {
                    $value = $raw[self::normalizedKey($name)] ?? '';
                    if ($value !== '') return $value;
                }
                return '';
            };

            $table = $read([
                'TableName', 'Table_Name', 'SourceTableName', 'SourceTable',
                'TypeTable', 'RefTable', 'TblName', 'TableCode', 'BaseTable', 'Table',
            ]);
            if ($table !== '' && self::normalizedKey((string) preg_replace('/^.*\./', '', $table)) !== self::normalizedKey($tableName)) {
                continue;
            }
            if (!$filteredAtSource && $table === '') {
                // Without server-side filtering, do not silently show types
                // belonging to a different TableName.
                continue;
            }

            $id = $read([
                'RecordID', 'DocTypeID', 'ServiceTypeID', 'DocumentTypeID', 'TypeID', 'ID',
                'DocTypeCode', 'ServiceTypeCode', 'DocumentTypeCode', 'TypeCode',
                'Type', 'DocType', 'ServiceType', 'DocumentType', 'Code', 'Value', 'Key',
            ]);
            $code = $read([
                'DocTypeCode', 'ServiceTypeCode', 'DocumentTypeCode', 'TypeCode',
                'DocCode', 'Type', 'DocType', 'ServiceType', 'DocumentType', 'Code',
                'Prefix', 'ShortCode', 'CodeValue', 'Value',
            ]);
            $label = $read([
                'DocTypeName', 'ServiceTypeName', 'DocumentTypeName', 'TypeName',
                'DocTypeDescription', 'DocTypeDesc', 'DisplayName', 'DisplayText',
                'Description', 'DocName', 'Name', 'Caption', 'Text', 'Label',
            ]);

            // Actual ERP USP returns RecordID (id), Type (code), Description (label).
            // Only return rows that are identifiable and have a code. No
            // synthetic codes are invented from generic labels/descriptions.
            if ($code === '') continue;
            if ($id === '') $id = $code; // Some ERPs expose only a service-type code.
            $id = self::limit($id, 100);
            $types[$id] = [
                'id' => $id,
                'code' => self::limit($code, 100),
                'label' => self::limit($label !== '' ? $label : $code, 200),
            ];
        }
        uasort($types, static fn (array $a, array $b): int =>
            strnatcasecmp($a['label'], $b['label']) ?: strnatcasecmp($a['code'], $b['code'])
        );
        return array_values($types);
    }

    /** @return array{id:string,code:string,label:string} */
    public function find(string $id, string $source): array
    {
        foreach ($this->all($source) as $type) {
            if (hash_equals($type['id'], $id)) return $type;
        }
        $source = strtoupper(trim($source));
        throw new RuntimeException("Service Type tidak tersedia di AS_{$source}. Pilih ulang dari database yang benar.");
    }

    private static function limit(string $value, int $characters): string
    {
        // mbstring is present in normal Laravel deployments. Keep this parser
        // testable on stripped-down CLI environments as well.
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $characters)
            : substr($value, 0, $characters);
    }

    private static function normalizedKey(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key));
    }
}
