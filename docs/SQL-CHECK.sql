DECLARE @date2 DATE = '2026-07-22';
DECLARE @date1 DATE = DATEADD(MONTH, DATEDIFF(MONTH, 0, @date2) - 1, 0);

USE [AS_INGCO];
SELECT TOP (20) *
FROM [dbo].[fn_PenjualanLini](@date1, @date2);

USE [AS_SMI];
SELECT TOP (20) *
FROM [dbo].[fn_PenjualanLini](@date1, @date2);

/*
Kolom minimal yang dibutuhkan dashboard:
- LiniPenjualan atau alias lini/channel
- LineTotal atau alias nilai penjualan

Dashboard memanggil function terpisah untuk DTD, LDTD, MTD, dan LMTD, sehingga InvoiceDate tidak wajib berada pada hasil function.

Untuk filter status item:
- Discontinue
- ClearenceSale / ClearanceSale / discount clearance
*/
