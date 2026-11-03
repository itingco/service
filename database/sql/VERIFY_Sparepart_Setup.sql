/* Jalankan terpisah pada DB_AppHub */
SELECT DB_NAME() AS DatabaseName,
       OBJECT_ID('dbo.ItemSimilar', 'U') AS ItemSimilar,
       OBJECT_ID('dbo.ItemSimmilar', 'U') AS ItemSimmilar;
-- Pilih salah satu tabel yang ada untuk PRODUCT_STRATEGY_SIMILAR_TABLE
-- Jika dbo.ItemSimilar ada:
-- SELECT TOP 20 ItemCode, SimilarItemCode FROM dbo.ItemSimilar;

/* Jalankan terpisah pada AS_INGCO dan AS_SMI */
SELECT DB_NAME() AS DatabaseName,
       OBJECT_ID('dbo.USP_Dashboard_SparepartStock', 'P') AS ProcedureInstalled;
SELECT PriceLevelID, PriceLevelName
FROM dbo.IC_PriceLevels
WHERE PriceLevelID = 1 OR UPPER(PriceLevelName) LIKE '%RETAIL%' OR UPPER(PriceLevelName) LIKE '%HET%';
