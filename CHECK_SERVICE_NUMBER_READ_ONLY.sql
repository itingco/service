-- READ-ONLY. Jalankan pada AS_INGCO atau AS_SMI, tidak melakukan perubahan data.
-- Ganti nilai @Type jika perlu.
DECLARE @Type varchar(50) = 'SC-ACH';
SELECT TOP (15) ServiceID, ServiceType, ServiceNumber, ServiceCounter, ReceivedDate, CreateDate
FROM dbo.IC_Services
WHERE ServiceType = @Type
ORDER BY ServiceID DESC;
SELECT ServiceType, MAX(ServiceCounter) AS MaxServiceCounter,
       MAX(TRY_CONVERT(int, RIGHT(ServiceNumber,
           NULLIF(CHARINDEX('/', REVERSE(ServiceNumber)),0)-1))) AS MaxSuffix
FROM dbo.IC_Services
WHERE ServiceType = @Type
GROUP BY ServiceType;
