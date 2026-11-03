-- JALANKAN PADA DB_AppHub (SQL Server). Tidak memodifikasi database ERP.
IF OBJECT_ID('dbo.StockWarehouseCategories','U') IS NULL
BEGIN
  CREATE TABLE dbo.StockWarehouseCategories (
    CategoryID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    CategoryName NVARCHAR(100) NOT NULL UNIQUE
  );
END;
IF OBJECT_ID('dbo.StockWarehouseCategoryMembers','U') IS NULL
BEGIN
  CREATE TABLE dbo.StockWarehouseCategoryMembers (
    CategoryID INT NOT NULL,
    SourceCode VARCHAR(10) NOT NULL,
    WarehouseID INT NOT NULL,
    CONSTRAINT PK_StockWarehouseCategoryMembers PRIMARY KEY(CategoryID,SourceCode,WarehouseID),
    CONSTRAINT FK_StockWarehouseCategoryMembers_Category FOREIGN KEY(CategoryID)
      REFERENCES dbo.StockWarehouseCategories(CategoryID) ON DELETE CASCADE,
    CONSTRAINT CK_StockWarehouseCategoryMembers_Source CHECK(SourceCode IN ('INGCO','SMI'))
  );
END;
