/* =============================================================
   CHECK CUSTOMER ANALYSIS REQUIREMENTS
   Jalankan pada AS_INGCO dan AS_SMI untuk memastikan field tersedia.
   ============================================================= */

SELECT TOP 10
    CustomerID,
    CustomerName,
    CustomerCode,
    SalesPersonID
FROM AR_Customers
ORDER BY CustomerID;

SELECT TOP 10
    SalesPersonID,
    SalesPersonName
FROM AR_SalesPersons
ORDER BY SalesPersonID;

DECLARE @EndDate date = CAST(GETDATE() AS date);
DECLARE @StartDate date = DATEADD(MONTH, -2, DATEFROMPARTS(YEAR(@EndDate), MONTH(@EndDate), 1));

SELECT TOP 10
    CustomerCode,
    CustomerName,
    SalesPersonName,
    InvoiceDate,
    InvoiceNumber,
    ItemCode,
    ItemName,
    Quantity,
    LineTotal
FROM dbo.fn_PenjualanLini(@StartDate, @EndDate)
ORDER BY InvoiceDate DESC, InvoiceNumber DESC;
