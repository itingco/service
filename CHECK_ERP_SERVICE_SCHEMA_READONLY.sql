/* HANYA SELECT: jalankan di SSMS satu database ERP per kali (AS_INGCO atau AS_SMI).
   Tidak ada CREATE/ALTER/INSERT/UPDATE/DELETE. */
SELECT DB_NAME() AS ActiveDatabase;
SELECT OBJECT_SCHEMA_NAME(c.object_id) AS SchemaName,
       OBJECT_NAME(c.object_id) AS TableName,
       c.name AS ColumnName, TYPE_NAME(c.user_type_id) AS SqlType,
       c.is_nullable AS IsNullable, c.is_identity AS IsIdentity,
       c.is_computed AS IsComputed,
       CASE WHEN c.default_object_id=0 THEN 0 ELSE 1 END AS HasDefault
FROM sys.columns c
WHERE c.object_id IN (OBJECT_ID('dbo.IC_Services'), OBJECT_ID('dbo.IC_ServiceSpareParts'), OBJECT_ID('dbo.IC_Items'))
  AND (
       c.object_id IN (OBJECT_ID('dbo.IC_Services'), OBJECT_ID('dbo.IC_ServiceSpareParts'))
       OR c.name IN ('ItemID', 'ItemCode', 'AverageCost')
  )
ORDER BY TableName, c.column_id;

SELECT TOP (15) ServiceID, ServiceNumber, ServiceCounter, ServiceType, CustomerID, CurrencyID
FROM dbo.IC_Services
ORDER BY ServiceID DESC;

SELECT CASE WHEN COL_LENGTH('dbo.IC_Items','AverageCost') IS NULL
     THEN 'NO: IC_Items does not have AverageCost column. Do NOT substitute RETAIL price.'
     ELSE 'YES: IC_Items.AverageCost exists; confirm it is the correct current ERP cost.'
     END AS AverageCostSourceCheck;
