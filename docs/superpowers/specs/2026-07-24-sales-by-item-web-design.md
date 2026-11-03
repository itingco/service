# Sales By Item Web Design

## Purpose

Create a standalone, read-only Laravel 10 application that replaces the Excel reporting workflow for `AS_INGCO` and `AS_SMI`. The user selects one database or both and supplies only an end date; the application derives the first day of that month and displays an aggregated Sales By Item report.

## User Flow

1. Open `/sales-by-item`.
2. Select `Semua Database`, `INGCO`, or `SMI`.
3. Select an end date.
4. Submit the filter.
5. Review KPI totals and the Sales By Item table.
6. Optionally search within the visible table or export the same filtered report to Excel/PDF.

## Architecture

- `SalesByItemRequest` validates query parameters.
- `SalesByItemFilter` owns the selected database and derived date range.
- `SqlServerSalesDataSource` calls `dbo.fn_PenjualanLini(?, ?)` using explicit Laravel SQL Server connections.
- `SalesRowNormalizer` maps possible function column aliases to a stable internal shape.
- `SalesByItemAggregator` groups by normalized item code and calculates totals, average price, contribution, and source label.
- `SalesByItemService` orchestrates one or both sources and rejects partial ALL results when a source fails.
- `SalesByItemController` renders Blade or downloads Excel/PDF from the same report object.

## Data Rules

- `end_date` uses `Y-m-d`.
- `start_date` is the first day of the same calendar month.
- ALL calls INGCO and SMI independently.
- Item codes are trimmed and uppercased before grouping.
- Equal item codes from both sources produce source `INGCO & SMI`.
- Rows without a usable item code are skipped.
- If result rows exist but mandatory code/quantity/sales columns are not recognized, the request fails with the actual function column list.
- No database writes are performed.

## UI

The page uses Bootstrap 5 from CDN and requires no Node build. It includes a compact filter card, four KPI cards, a sticky-header result table, client-side item search, clear empty/error states, and Excel/PDF export buttons preserving the active filter.

## Error Handling

Each connection failure is wrapped in `SalesDataException` with the source database. An ALL request never renders partial data. The page provides a practical message covering network access, SQLSRV drivers, and the table-valued function. Full technical exceptions remain in Laravel logs.

## Deployment

The project targets PHP 8.1+, Laravel 10, SQL Server ODBC plus `sqlsrv`/`pdo_sqlsrv`, Laravel Excel, and Laravel DomPDF. Credentials are configured through `.env`. Windows/XAMPP installation, column mapping, and SQL verification are documented in `docs/`.
