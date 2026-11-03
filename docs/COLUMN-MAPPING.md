# Mapping Kolom `fn_PenjualanLini`

Project memiliki dua pemetaan yang sengaja dipisahkan.

## Sales Dashboard lama

Halaman `/sales-dashboard` tetap memakai pemetaan alias/fuzzy pada:

```text
app/Support/SalesDashboardRowNormalizer.php
```

Perilaku lama tidak diubah oleh penambahan KPI Dashboard.

## Sales KPI Dashboard

Halaman `/sales-kpi` memakai nama kolom **persis** dari contoh `Book1(3).xlsx` melalui:

```text
app/Support/SalesKpiRowNormalizer.php
```

### Kolom wajib untuk skor

| Kebutuhan | Nama kolom persis | Aturan |
|---|---|---|
| Pengelompokan | `LiniPenjualan` | Nilainya menjadi lini bisnis, misalnya `JAKARTA` dan `GERAI KATAMSO` |
| Omzet | `LineTotal` | Dijumlahkan apa adanya, termasuk nilai negatif |
| Discontinued | `Discontinue` | Nilai numerik `1` dianggap discontinued |
| Item Reject | `StatusClearanceSale` | Field legacy; teks `CLEARANCE SALE` atau `ITEM REJECT` dibaca sebagai Item Reject |
| Grade | `Grade` | KPI menghitung Grade `A` dan `B` |

### Kolom detail

```text
StatusItem
Quantity
ClearanceSaleDiscount
InvoiceDate
InvoiceNumber
ItemCode
ItemName
```

`ClearanceSaleDiscount` tetap dipertahankan sebagai field legacy/informasi. Nilainya tidak dipakai untuk menentukan apakah item merupakan Item Reject.

Jika kolom wajib hilang, halaman menampilkan pesan aman seperti:

```text
Kolom StatusClearanceSale tidak ditemukan pada hasil fn_PenjualanLini.
```
