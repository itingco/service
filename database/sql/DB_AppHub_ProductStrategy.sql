USE [DB_AppHub];
GO

/*
   Product Strategy master tables used by sales-dashboard.
   Safe to run repeatedly. Existing data is not deleted.

   Update 2026-10-03:
   - dbo.ItemFocus sekarang memiliki kolom ItemName NVARCHAR(255) NULL.
   - Jika tabel lama sudah ada, script otomatis menambahkan ItemName.
*/

IF OBJECT_ID(N'dbo.ItemFocus', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ItemFocus
    (
        id            INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ItemFocus PRIMARY KEY,
        ItemCode      NVARCHAR(100) NOT NULL,
        ItemName      NVARCHAR(255) NULL,
        ItemFocusType NVARCHAR(80) NOT NULL,
        Updated       DATETIME2(0) NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.ItemFocus', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.ItemFocus', N'ItemName') IS NULL
BEGIN
    ALTER TABLE dbo.ItemFocus
        ADD ItemName NVARCHAR(255) NULL;
END;
GO

IF OBJECT_ID(N'dbo.ItemSimilar', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ItemSimilar
    (
        id              INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ItemSimilar PRIMARY KEY,
        ItemCode        NVARCHAR(100) NOT NULL,
        SimilarItemCode NVARCHAR(100) NOT NULL
    );
END;
GO

IF NOT EXISTS
(
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.ItemFocus')
      AND name = N'UX_ItemFocus_ItemCode'
)
BEGIN
    CREATE UNIQUE INDEX UX_ItemFocus_ItemCode
        ON dbo.ItemFocus(ItemCode);
END;
GO

IF NOT EXISTS
(
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.ItemSimilar')
      AND name = N'UX_ItemSimilar_ItemCode_SimilarItemCode'
)
BEGIN
    CREATE UNIQUE INDEX UX_ItemSimilar_ItemCode_SimilarItemCode
        ON dbo.ItemSimilar(ItemCode, SimilarItemCode);
END;
GO

SELECT TOP (100)
    id,
    ItemCode,
    ItemName,
    ItemFocusType,
    Updated
FROM dbo.ItemFocus
ORDER BY id DESC;

SELECT TOP (100)
    id,
    ItemCode,
    SimilarItemCode
FROM dbo.ItemSimilar
ORDER BY id DESC;
GO
