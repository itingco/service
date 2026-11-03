/*
    READ ONLY - Jalankan di SSMS untuk AS_INGCO.
    Untuk SMI ganti USE [AS_INGCO] menjadi USE [AS_SMI].
    Nomor berikut dihitung per Service Type dan tahun-bulan WIB saat ini.
    Hanya SELECT; tidak membuat/mengubah tabel ERP.
*/
USE [AS_INGCO];
GO
SET NOCOUNT ON;
DECLARE @ServiceType nvarchar(50) = N'SC-ACH';
DECLARE @DateYYMMDD char(6) = RIGHT(CONVERT(char(8), DATEADD(hour, 7, SYSUTCDATETIME()), 112), 6);
DECLARE @Prefix nvarchar(30) = (
    SELECT TOP (1)
        LEFT(ServiceNumber, CHARINDEX('/', ServiceNumber) - 1)
    FROM dbo.IC_Services
    WHERE ServiceType = @ServiceType
      AND CHARINDEX('/', ServiceNumber) > 1
    ORDER BY ServiceID DESC
);
IF @Prefix IS NULL
BEGIN
    SELECT 'Tidak ada nomor historis untuk Service Type; prefix perlu ditetapkan.' AS Keterangan;
    RETURN;
END;
DECLARE @MonthPrefix nvarchar(150) = @Prefix + N'/' + @ServiceType + N'/' + LEFT(@DateYYMMDD, 4);
DECLARE @PrefixLength int = LEN(@MonthPrefix);

-- Transaksi hanya pada bulan berjalan (YYMM)
SELECT ServiceID, ServiceType, ServiceNumber, ServiceCounter, CreateDate
FROM dbo.IC_Services
WHERE ServiceType = @ServiceType
  AND LEFT(LTRIM(RTRIM(ServiceNumber)), @PrefixLength) = @MonthPrefix
  AND SUBSTRING(LTRIM(RTRIM(ServiceNumber)), @PrefixLength + 3, 1) = '/'
  AND TRY_CONVERT(int, SUBSTRING(LTRIM(RTRIM(ServiceNumber)), @PrefixLength + 1, 2)) BETWEEN 1 AND 31
  AND TRY_CONVERT(bigint, SUBSTRING(LTRIM(RTRIM(ServiceNumber)), @PrefixLength + 4, 50)) IS NOT NULL
ORDER BY ServiceID DESC;

-- Persis seperti perhitungan counter di Laravel setelah patch.
WITH DalamBulan AS (
    SELECT
      TRY_CONVERT(bigint, ServiceCounter) AS ServiceCounter,
      TRY_CONVERT(bigint, SUBSTRING(LTRIM(RTRIM(ServiceNumber)), @PrefixLength + 4, 50)) AS Suffix
    FROM dbo.IC_Services
    WHERE ServiceType = @ServiceType
      AND LEFT(LTRIM(RTRIM(ServiceNumber)), @PrefixLength) = @MonthPrefix
      AND SUBSTRING(LTRIM(RTRIM(ServiceNumber)), @PrefixLength + 3, 1) = '/'
      AND TRY_CONVERT(int, SUBSTRING(LTRIM(RTRIM(ServiceNumber)), @PrefixLength + 1, 2)) BETWEEN 1 AND 31
      AND TRY_CONVERT(bigint, SUBSTRING(LTRIM(RTRIM(ServiceNumber)), @PrefixLength + 4, 50)) IS NOT NULL
)
SELECT
    @ServiceType AS ServiceType,
    @MonthPrefix AS PrefixBulan,
    COUNT(*) AS JumlahNomorDiBulanIni,
    COALESCE(MAX(ServiceCounter), 0) AS MaxServiceCounterBulanIni,
    COALESCE(MAX(Suffix), 0) AS MaxSuffixBulanIni,
    1 + CASE WHEN COALESCE(MAX(ServiceCounter), 0) > COALESCE(MAX(Suffix), 0)
             THEN COALESCE(MAX(ServiceCounter), 0) ELSE COALESCE(MAX(Suffix), 0) END AS CounterBerikutnya,
    @Prefix + N'/' + @ServiceType + N'/' + @DateYYMMDD + N'/' +
    RIGHT('0000' + CONVERT(varchar(16),
        1 + CASE WHEN COALESCE(MAX(ServiceCounter), 0) > COALESCE(MAX(Suffix), 0)
                 THEN COALESCE(MAX(ServiceCounter), 0) ELSE COALESCE(MAX(Suffix), 0) END
    ), 4) AS ContohNomorBaru
FROM DalamBulan;
