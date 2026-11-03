# Sales Insights & R&D Dashboard

## URL

- Sales Dashboard operasional tetap: `/sales-dashboard`
- Dashboard analisis lanjutan: `/sales-insights`

Filter `database`, `end_date`, dan `item_status` diteruskan saat user menekan tombol **Dashboard Analisis Lanjutan**.

## Management Analysis

Dashboard baru menampilkan:

- total target, realisasi MTD, sisa target, dan forecast EOM;
- status pace per LiniPenjualan;
- required daily sales dibanding average daily sales;
- potential risk gap pada akhir bulan;
- action center berdasarkan achievement, forecast, growth, dan kebutuhan harian;
- top/bottom achievement dan growth;
- customer concentration, top salesperson, top item, grade mix, dan status item;
- grafik achievement, gap target, dan cumulative MTD vs target pace.

## R&D Analysis

Analisis produk membandingkan MTD dengan periode sama bulan lalu dan menghasilkan signal:

- **SCALE UP**: growth minimal 20% dan dibeli minimal 3 customer;
- **PERMINTAAN BARU**: terjual pada MTD tetapi tidak pada LMTD;
- **VALIDASI PASAR**: growth minimal 20% tetapi customer kurang dari 3;
- **RISIKO KONSENTRASI**: minimal 70% omzet produk berasal dari satu customer;
- **EVALUASI**: demand turun minimal 20%;
- **PERMINTAAN HILANG**: terjual pada LMTD tetapi nol pada MTD;
- **FASE KELUAR**: item discontinued atau clearance masih bergerak;
- **STABIL**: tidak masuk kondisi prioritas di atas.

Setiap produk memiliki data pendukung:

- omzet MTD dan LMTD;
- growth;
- quantity;
- jumlah customer;
- share customer terbesar;
- LiniPenjualan utama dan share-nya;
- rata-rata input Discount + Discount2 tertimbang omzet;
- grade, discontinued, dan clearance;
- rekomendasi tindakan untuk R&D.

## Penggunaan oleh R&D

- **Demand baru** digunakan sebagai daftar awal validasi produk, repeat order, margin, dan stok.
- **Demand hilang** digunakan untuk investigasi stok kosong, harga, fitur, kompetitor, atau customer yang berhenti membeli.
- **Customer concentration** mencegah keputusan scale-up berdasarkan satu customer saja.
- **Discount effectiveness watch** menunjukkan item yang tetap turun meski memperoleh diskon.
- **Grade demand** membantu mengevaluasi komposisi portfolio Grade A, B, dan lainnya.
- **Channel–product fit** menunjukkan LiniPenjualan terkuat untuk uji pasar dan peluncuran produk.

## Export

- Export lama pada `/sales-dashboard` tetap berisi `Ringkasan Sales` dan `Data Mentah`.
- Export baru pada `/sales-insights` berisi:
  - `Management Insights`
  - `R&D Product Signals`
  - `R&D Demand Watch`
  - `R&D Portfolio`
  - `Data Mentah MTD`
