@if($item['warehouses'] !== [])
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 spare-table">
            <thead>
                <tr>
                    <th>Database</th>
                    <th>Gudang</th>
                    <th class="text-end">Stok (UOM terkecil)</th>
                    <th class="text-end">Harga RETAIL / HET (Price1)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($item['warehouses'] as $location)
                    <tr>
                        <td><span class="spare-source">AS_{{ $location['source'] }}</span></td>
                        <td>
                            <strong>{{ $location['warehouse_name'] ?: ($location['warehouse_code'] ?: 'Warehouse #'.$location['warehouse_id']) }}</strong>
                            @if($location['warehouse_code'])<div class="text-muted small">{{ $location['warehouse_code'] }}</div>@endif
                        </td>
                        <td class="text-end fw-bold">{{ $qty($location['qty']) }}</td>
                        <td class="text-end">
                            @if(($location['retail_price'] ?? null) !== null)
                                <strong>{{ $rupiah($location['retail_price']) }}</strong>
                                <div class="text-success small">RETAIL</div>
                            @elseif(($location['reference_price'] ?? null) !== null)
                                <span class="text-muted">RETAIL belum tersedia</span>
                                <div class="small text-warning-emphasis">
                                    Referensi {{ $location['reference_level'] ?? 'HDB' }}: {{ $rupiah($location['reference_price']) }}
                                    <span class="text-muted">(bukan HET)</span>
                                </div>
                            @else
                                <span class="text-muted">Harga RETAIL belum tersedia</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="spare-empty py-3">{{ ($incomplete ?? false) ? 'Data stok belum terverifikasi: satu atau lebih sumber ERP gagal dibaca. Jangan menyimpulkan stok kosong.' : ($item['exists'] ? 'Stok tersedia tidak ditemukan pada gudang yang diperiksa.' : 'Kode ini tidak ditemukan dalam master item pada database yang diperiksa.') }}</div>
@endif
