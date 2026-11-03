-- READ ONLY: jalankan di AS_INGCO lalu AS_SMI.
-- Memeriksa apakah level RETAIL/HDB dan item yang relevan tersedia.
SELECT DB_NAME() AS DatabaseName, PriceLevelID, PriceLevelName
FROM dbo.IC_PriceLevels
WHERE UPPER(LTRIM(RTRIM(PriceLevelName))) IN ('RETAIL', 'HDB')
ORDER BY PriceLevelName;

SELECT DB_NAME() AS DatabaseName, I.ItemCode, I.ItemName,
       PL.PriceLevelID, PL.PriceLevelName, IPL.Price1,
       CASE WHEN IPL.ItemID IS NULL THEN 'TIDAK ADA PRICE LIST'
            WHEN IPL.Price1 IS NULL THEN 'NULL'
            WHEN IPL.Price1 <= 0 THEN 'NOL / NONPOSITIF'
            ELSE 'TERSEDIA' END AS StatusHarga
FROM dbo.IC_Items AS I
CROSS JOIN dbo.IC_PriceLevels AS PL
LEFT JOIN dbo.IC_ItemPriceList AS IPL
 ON IPL.ItemID = I.ItemID AND IPL.PriceLevelID = PL.PriceLevelID
WHERE I.ItemCode IN ('RGH9028-SP-38', 'RH10508-SP-35')
  AND UPPER(LTRIM(RTRIM(PL.PriceLevelName))) IN ('RETAIL', 'HDB')
ORDER BY I.ItemCode, PL.PriceLevelName, IPL.Price1 DESC;
