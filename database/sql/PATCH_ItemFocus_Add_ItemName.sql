USE [DB_AppHub];
GO

/*
    PATCH KHUSUS DATABASE EXISTING
    =============================
    Tujuan: menambah kolom ItemName pada dbo.ItemFocus tanpa menghapus data lama.
    Aman dijalankan ulang.
*/

IF OBJECT_ID(N'dbo.ItemFocus', N'U') IS NULL
BEGIN
    RAISERROR('dbo.ItemFocus belum ada. Jalankan DB_AppHub_ProductStrategy.sql terlebih dahulu.', 16, 1);
    RETURN;
END;
GO

IF COL_LENGTH(N'dbo.ItemFocus', N'ItemName') IS NULL
BEGIN
    PRINT 'Menambahkan kolom dbo.ItemFocus.ItemName ...';

    ALTER TABLE dbo.ItemFocus
        ADD ItemName NVARCHAR(255) NULL;

    PRINT 'Kolom ItemName berhasil ditambahkan.';
END
ELSE
BEGIN
    PRINT 'Kolom ItemName sudah ada. Tidak ada perubahan schema.';
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
GO
