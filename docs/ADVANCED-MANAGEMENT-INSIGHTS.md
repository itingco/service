# Advanced Sales Management Insights

Modul ini memperkuat Sales Dashboard menjadi laporan kontrol manajemen yang menjawab:

1. Apa yang sedang terjadi?
2. Mengapa target berisiko tidak tercapai?
3. Lini mana yang harus segera ditindaklanjuti?

## Pace

- `TARGET TERCAPAI`: achievement minimal 100%.
- `AHEAD`: forecast EOM minimal 110% target.
- `ON TRACK`: forecast EOM 90% sampai kurang dari 110% target.
- `BEHIND`: forecast EOM di bawah 90% target.
- `TARGET BELUM DIATUR`: monthly target nol.

## Action Center

Peringatan dibuat ketika salah satu kondisi berikut terjadi:

- target belum diatur;
- achievement di bawah 70%;
- forecast akhir bulan di bawah 90%;
- growth MTD negatif;
- required daily sales lebih besar daripada average daily sales.

## Analisis detail

Analisis customer, salesperson, item, grade, dan status item berasal dari data MTD hasil `dbo.fn_PenjualanLini`. Karena itu kualitas analisis mengikuti kelengkapan kolom function tersebut.

## Ekspor

Excel sekarang berisi tiga sheet:

1. `Ringkasan Sales`
2. `Management Insights`
3. `Data Mentah`
