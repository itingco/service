/*
  INSTALASI: eksekusi script ini secara TERPISAH pada database AS_INGCO DAN AS_SMI.
  SQL Server: stored procedure read-only; tidak mengubah data stok.
  Return stock in SMALLEST UOM, harga retail = Price1 untuk level yang dipilih.
  Sesuaikan SPAREPART_RETAIL_PRICE_LEVEL_ID di .env (default 1 = HET).
  Dokumen ini menurunkan cara hitung stok dari USP_Dashboard_SisaStokWarehouse.
  Catatan: proses USP_IC_Items_Calculate bisa berat pada database besar.
*/
CREATE OR ALTER PROCEDURE dbo.USP_Dashboard_SparepartStock
    @ItemCodes NVARCHAR(MAX),
    @PerDate DATE = NULL,
    @RetailPriceLevelID INT = 1
AS
BEGIN
    SET NOCOUNT ON;
    IF @PerDate IS NULL SET @PerDate = CAST(GETDATE() AS DATE);
    IF @ItemCodes IS NULL OR LEN(LTRIM(RTRIM(@ItemCodes))) = 0 RETURN;
    IF @RetailPriceLevelID IS NULL OR @RetailPriceLevelID < 1
        THROW 50001, 'Retail PriceLevelID harus positif.', 1;

    -- Tokenizer, tidak membutuhkan STRING_SPLIT/OPENJSON compatibility level.
    CREATE TABLE #RequestedCodes (ItemCode NVARCHAR(100) NOT NULL PRIMARY KEY);
    DECLARE @s NVARCHAR(MAX) = @ItemCodes + N',', @pos INT, @token NVARCHAR(100);
    WHILE LEN(@s) > 0
    BEGIN
        SET @pos = CHARINDEX(N',', @s);
        IF @pos <= 0 BREAK;
        SET @token = UPPER(LTRIM(RTRIM(LEFT(@s, @pos - 1))));
        IF @token <> N'' AND NOT EXISTS (SELECT 1 FROM #RequestedCodes WHERE ItemCode = @token)
            INSERT INTO #RequestedCodes(ItemCode) VALUES (@token);
        SET @s = SUBSTRING(@s, @pos + 1, LEN(@s));
    END;

    CREATE TABLE #SelectedItems (ItemID INT NOT NULL PRIMARY KEY);
    INSERT INTO #SelectedItems (ItemID)
    SELECT I.ItemID FROM dbo.IC_Items AS I
    INNER JOIN #RequestedCodes AS R ON UPPER(LTRIM(RTRIM(I.ItemCode))) = R.ItemCode;

    CREATE TABLE #SelectedWarehouses (WarehouseID INT NOT NULL PRIMARY KEY);
    INSERT INTO #SelectedWarehouses (WarehouseID)
    SELECT W.WarehouseID FROM dbo.IC_Warehouses AS W;

    CREATE TABLE #ItemBalancePerLocation
    (
        ItemID INT, WarehouseID INT, UOMLevel TINYINT, Balance MONEY
    );
    CREATE INDEX IX_SparepartBalance ON #ItemBalancePerLocation(ItemID, WarehouseID, UOMLevel);

    IF EXISTS (SELECT 1 FROM #SelectedItems)
    BEGIN
        -- Same verified calculation contract as USP_Dashboard_SisaStokWarehouse.
        EXEC dbo.USP_IC_Items_Calculate
            @AllItems = 0,
            @PerDate = @PerDate,
            @OutputKind = 'PerLocationBalanceReports',
            @OutputTable = '#ItemBalancePerLocation',
            @ExtraJoinCondition = 'JOIN #SelectedWarehouses SWH ON SWH.WarehouseID = $[OutletField]',
            @IncludeBroken = 1,
            @IncludeConsignmentOutlets = 1,
            @GoodStockOnly = NULL;
    END;

    SELECT B.ItemID, B.WarehouseID,
        SUM(dbo.UDF_Common_ConvertToSmallestUOMEx(
            I.Packing2, I.Packing3, I.Packing4, B.Balance, B.UOMLevel
        )) AS OnHandQty
    INTO #Stock
    FROM #ItemBalancePerLocation AS B
    INNER JOIN #SelectedItems AS S ON S.ItemID = B.ItemID
    INNER JOIN dbo.IC_Items AS I ON I.ItemID = B.ItemID
    GROUP BY B.ItemID, B.WarehouseID;

    SELECT
        I.ItemCode AS ItemCode,
        I.ItemName AS ItemName,
        W.WarehouseID AS WarehouseID,
        W.WarehouseCode AS WarehouseCode,
        W.Name AS WarehouseName,
        CAST(COALESCE(S.OnHandQty, 0) AS DECIMAL(28,4)) AS OnHandQty,
        CAST(P.Price1 AS DECIMAL(28,4)) AS RetailPrice
    FROM #SelectedItems AS SI
    INNER JOIN dbo.IC_Items AS I ON I.ItemID = SI.ItemID
    LEFT JOIN #Stock AS S ON S.ItemID = I.ItemID AND S.OnHandQty <> 0
    LEFT JOIN dbo.IC_Warehouses AS W ON W.WarehouseID = S.WarehouseID
    OUTER APPLY (
        SELECT TOP (1) IPL.Price1
        FROM dbo.IC_ItemPriceList AS IPL
        WHERE IPL.ItemID = I.ItemID AND IPL.PriceLevelID = @RetailPriceLevelID
    ) AS P
    ORDER BY I.ItemCode, W.WarehouseCode;
END;
GO

-- Contoh verifikasi, jalankan pada kedua database:
-- EXEC dbo.USP_Dashboard_SparepartStock @ItemCodes=N'ABCD-SP-1,BCDG-SP-7,ABCD,BCDG', @RetailPriceLevelID=1;
