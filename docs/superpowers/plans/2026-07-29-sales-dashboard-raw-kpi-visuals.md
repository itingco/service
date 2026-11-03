# Sales Dashboard Raw Data and KPI Visuals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove Sales Dashboard charts, add exportable raw transaction data and contribution metrics, and add exportable KPI detail tables plus pie and vertical bar charts.

**Architecture:** Extend the Sales Dashboard report with normalized MTD detail rows and line contribution. Serve raw rows through a protected server-side endpoint and export summary plus raw data as an Excel workbook. Keep KPI calculations unchanged while enhancing chart data, custom canvas rendering, and detail-specific Excel exports.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, custom JavaScript canvas charts, Maatwebsite Excel, PHPUnit/manual PHP verification.

## Global Constraints

- Existing filters and KPI formulas remain unchanged.
- Sales Dashboard summary remains visible; only its charts are removed.
- Sales raw data uses `fn_PenjualanLini` current MTD rows and includes source database.
- Contribution means line MTD divided by total MTD for the active Sales Dashboard filters.
- KPI detail exports include all rows matching active filters and search, not only the visible page.
- No Chart.js dependency is added.

---

### Task 1: Sales raw data model and contribution
- [ ] Extend normalizer fields and report details.
- [ ] Add contribution to line rows and raw rows.
- [ ] Add tests and manual assertions.

### Task 2: Sales raw DataTable and Excel workbook
- [ ] Add request, query service, controller endpoint, route, Blade table, and JavaScript.
- [ ] Replace one-sheet export with summary and raw-data sheets.
- [ ] Remove Sales Dashboard chart rendering.

### Task 3: KPI detail exports and charts
- [ ] Add detail export endpoint and workbook.
- [ ] Add export controls to all detail tables.
- [ ] Add pie chart and vertical bar charts to summary and convert tab charts to vertical bars.

### Task 4: Verification and packaging
- [ ] Run syntax checks, manual tests, JavaScript checks, and archive verification.
- [ ] Create patch and full-project ZIP files.
