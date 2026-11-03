# Perhitungan Sales KPI Dashboard

## Ruang lingkup

Dashboard `/sales-kpi` selalu menghitung data gabungan `AS_INGCO + AS_SMI` per nilai **`LiniPenjualan`** dari `dbo.fn_PenjualanLini`. Skor maksimum aplikasi adalah **90 poin**. Monitoring harian 10% berada di luar aplikasi.

## Data dan target gabungan

```text
Omzet Aktual Gabungan = Omzet AS_INGCO + Omzet AS_SMI
```

Satu target aktif disimpan per `LiniPenjualan` pada SQLite lokal dan berlaku untuk semua periode:

```text
database/database.sqlite
```

Target dapat diedit melalui `/sales-kpi/targets`. Nama lini dinormalisasi dengan trim, penyatuan spasi berulang, dan huruf besar.

Jika target belum diatur, komponen Target Omzet dan Discontinued/Item Reject menjadi `N/A`. Growth dan Grade tetap dihitung ketika data dasarnya tersedia. Skor akhir dinormalisasi dari komponen yang dapat dihitung.

## 1. Target Omzet — maksimum 40 poin

```text
Achievement = Omzet aktual gabungan / Target gabungan
Skor = MIN(MAX(Achievement / 70% × 40, 0), 40)
```

## 2. Growth vs Last Month — maksimum 10 poin

Jumlah hari kedua periode disamakan. Omzet masing-masing database dijumlahkan dahulu, kemudian growth dihitung:

```text
Growth = (Omzet gabungan sekarang - Omzet gabungan sebelumnya)
         / Omzet gabungan sebelumnya

Skor = MIN(MAX(MAX(Growth, 0) / 10% × 10, 0), 10)
```

Jika omzet pembanding nol, Growth menjadi `N/A`.

## 3. Discontinued dan Clearance — maksimum 20 poin

```text
Target khusus = 9% × Target gabungan
Realisasi = Omzet Discontinued + Omzet Clearance
Skor = MIN(MAX(Realisasi / Target khusus × 20, 0), 20)
```

Item berstatus Discontinued sekaligus Clearance sengaja dihitung pada kedua subtotal.

## 4. Grade A — maksimum 12 poin

```text
Kontribusi A = Omzet Grade A / Total omzet aktual gabungan lini
Skor A = MIN(MAX(Kontribusi A / 35% × 12, 0), 12)
```

## 5. Grade B — maksimum 8 poin

```text
Kontribusi B = Omzet Grade B / Total omzet aktual gabungan lini
Skor B = MIN(MAX(Kontribusi B / 20% × 8, 0), 8)
```

Jika total omzet nol, Grade A dan B menjadi `N/A`.

## Normalisasi

```text
Skor akhir = Poin diperoleh / Maksimum poin tersedia × 90
```

## Status

```text
Baik             : skor >= 80
Perlu Perhatian  : skor 60 sampai 79,99
Kurang           : skor < 60
```

## Total seluruh lini

```text
Total tertimbang = Σ(Skor lini × Target lini) / Σ(Target lini)
```

Lini tanpa target tetap ditampilkan tetapi tidak menjadi bobot total.

## Filter

Filter `LiniPenjualan` membatasi seluruh dashboard, grafik, detail, dan ekspor ke lini terpilih. Filter Discontinued, Clearance, Grade, dan pencarian hanya membatasi detail transaksi.

## Kegagalan database

Mode gabungan bersifat all-or-nothing. Jika `AS_INGCO` atau `AS_SMI` gagal, aplikasi tidak menghitung atau menampilkan data parsial.
