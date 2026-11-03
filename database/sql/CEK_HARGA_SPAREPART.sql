/*
DIAGNOSTIK HARGA SPAREPART - READ ONLY
Jalankan di SSMS, pilih database AS_INGCO. Ulangi pada AS_SMI jika diperlukan.
Tidak membuat USP, tidak mengubah atau menghapus data.

Modul Laravel saat ini membaca:
  dbo.IC_Items.ItemID = dbo.IC_ItemPriceList.ItemID
  dbo.IC_ItemPriceList.PriceLevelID = SPAREPART_RETAIL_PRICE_LEVEL_ID (.env; default 1)
  dbo.IC_ItemPriceList.Price1 = retail_price
Jika harga tampil 'Harga belum tersedia', nilai RetailPrice yang dikembalikan Laravel adalah NULL.

Ubah @ExpectedPriceLevelID sesuai SPAREPART_RETAIL_PRICE_LEVEL_ID pada .env!
*/
SET NOCOUNT ON;

DECLARE @ItemCode NVARCHAR(100) = N'RGH9028-SP-38';  -- harga tidak muncul
DECLARE @ComparisonItem NVARCHAR(100) = N'RH10508-SP-35'; -- harga muncul pada screenshot
DECLARE @ExpectedPriceLevelID INT = 1; -- sesuaikan dengan konfigurasi aktif

PRINT N'01. IDENTITAS DATABASE DAN MASTER BARANG';
SELECT DB_NAME() AS DatabaseName, GETDATE() AS CheckedAt,
       @ExpectedPriceLevelID AS PriceLevelUsedByLaravel;

SELECT I.ItemID, I.ItemCode, I.ItemName
FROM dbo.IC_Items AS I
WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) IN (UPPER(@ItemCode), UPPER(@ComparisonItem))
ORDER BY I.ItemCode;

PRINT N'02. SEMUA PRICE LEVEL DI DATABASE AKTIF';
SELECT PL.PriceLevelID, PL.PriceLevelName,
       CASE WHEN PL.PriceLevelID = @ExpectedPriceLevelID THEN N'<-- DIPAKAI MODUL LARAVEL' ELSE N'' END AS Info
FROM dbo.IC_PriceLevels AS PL
ORDER BY PL.PriceLevelID;

PRINT N'03. SELURUH PRICE LIST UNTUK DUA SPAREPART (TANPA FILTER LEVEL)';
SELECT I.ItemCode, I.ItemName, I.ItemID,
       P.PriceLevelID, PL.PriceLevelName,
       P.Price1 AS HargaPrice1,
       CASE
           WHEN P.PriceLevelID IS NULL THEN N'BELUM ADA BARIS PRICE LIST'
           WHEN P.Price1 IS NULL THEN N'PRICE1 NULL'
           WHEN P.Price1 = 0 THEN N'PRICE1 = 0'
           WHEN P.Price1 > 0 THEN N'HARGA ADA'
           ELSE N'PRICE1 NEGATIF / PERIKSA'
       END AS StatusHarga
FROM dbo.IC_Items AS I
LEFT JOIN dbo.IC_ItemPriceList AS P ON P.ItemID = I.ItemID
LEFT JOIN dbo.IC_PriceLevels AS PL ON PL.PriceLevelID = P.PriceLevelID
WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) IN (UPPER(@ItemCode), UPPER(@ComparisonItem))
ORDER BY I.ItemCode, P.PriceLevelID;

PRINT N'04. APA YANG AKAN TERBACA LARAVEL PADA LEVEL TERPILIH';
SELECT I.ItemCode, I.ItemName, I.ItemID,
       @ExpectedPriceLevelID AS SelectedPriceLevelID,
       PL.PriceLevelName AS SelectedLevelName,
       COALESCE(P.TotalRows, 0) AS JumlahBarisPriceList,
       P.TotalNull AS JumlahHargaNull,
       P.TotalZero AS JumlahHargaNol,
       P.MinPrice1 AS HargaMin,
       P.MaxPrice1 AS HargaMax,
       CASE
          WHEN COALESCE(P.TotalRows, 0) = 0 THEN N'TIDAK ADA PRICE LIST PADA PRICE LEVEL INI'
          WHEN P.TotalNull = P.TotalRows THEN N'SEMUA PRICE1 NULL'
          WHEN P.TotalZero = P.TotalRows THEN N'SEMUA PRICE1 NOL'
          WHEN P.TotalRows > 1 AND (P.TotalNull > 0 OR P.MinPrice1 <> P.MaxPrice1)
               THEN N'ADA DUPLIKAT/VARIASI: TOP (1) TANPA ORDER BY BISA AMBIL BARIS BERBEDA'
          WHEN P.MaxPrice1 > 0 THEN N'HARGA POSITIF TERSEDIA'
          ELSE N'PERIKSA NILAI HARGA'
       END AS Diagnosis
FROM dbo.IC_Items AS I
LEFT JOIN dbo.IC_PriceLevels AS PL ON PL.PriceLevelID = @ExpectedPriceLevelID
OUTER APPLY (
    SELECT COUNT(*) AS TotalRows,
           COUNT(CASE WHEN P0.Price1 IS NULL THEN 1 END) AS TotalNull,
           COUNT(CASE WHEN P0.Price1 = 0 THEN 1 END) AS TotalZero,
           MIN(P0.Price1) AS MinPrice1,
           MAX(P0.Price1) AS MaxPrice1
    FROM dbo.IC_ItemPriceList AS P0
    WHERE P0.ItemID = I.ItemID AND P0.PriceLevelID = @ExpectedPriceLevelID
) AS P
WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) IN (UPPER(@ItemCode), UPPER(@ComparisonItem))
ORDER BY I.ItemCode;

PRINT N'05. DETIL ASLI PRICE LIST: CEK JIKA ADA KOLOM UOM / PRICE LAIN';
SELECT I.ItemCode, P.*
FROM dbo.IC_ItemPriceList AS P
JOIN dbo.IC_Items AS I ON I.ItemID = P.ItemID
WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) IN (UPPER(@ItemCode), UPPER(@ComparisonItem))
ORDER BY I.ItemCode, P.PriceLevelID;

PRINT N'06. DAFTAR NAMA KOLOM PRICE LIST (UNTUK VALIDASI FIELD HARGA ERP)';
SELECT C.name AS ColumnName, TY.name AS DataType
FROM sys.columns AS C
JOIN sys.types AS TY ON C.user_type_id = TY.user_type_id
WHERE C.object_id = OBJECT_ID(N'dbo.IC_ItemPriceList')
ORDER BY C.column_id;

/*
PANDUAN MEMBACA:
- Step 01 tidak muncul kode => kode/item tidak ada di database tersebut.
- Step 03 Price1 terisi di level lain => cek level HET/RETAIL yang benar (Step 02).
- Step 04 'TIDAK ADA PRICE LIST...' => sebab utama tampilan 'Harga belum tersedia'.
- Step 04 'SEMUA PRICE1 NULL' => harga pada level tersebut memang belum diisi.
- Step 04 banyak row dengan harga bervariasi => Laravel OUTER APPLY TOP(1) tanpa ORDER BY
  dapat memilih row NULL; sebaiknya cek dan definisikan aturan pemilihan harga.
- Harga nol ditampilkan Rp 0 oleh kode Blade sekarang (berbeda dengan harga NULL).
JANGAN UPDATE harga atau mengubah level secara massal tanpa konfirmasi mapping ERP.
*/
