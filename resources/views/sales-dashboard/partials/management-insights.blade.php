<section class="management-insights mb-4" aria-label="Management insights">
    <div class="insight-section-head">
        <div>
            <h2 class="h5 fw-bold mb-1">Management Control &amp; Action Center</h2>
            <div class="small text-secondary">Ringkasan pace penjualan, risiko target, dan area yang perlu segera ditindaklanjuti.</div>
        </div>
        <span class="status-pill">{{ count($insights->actions) }} tindakan terdeteksi</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2">
            <div class="insight-kpi-card">
                <div class="insight-kpi-label">Target Bulanan</div>
                <div class="insight-kpi-value">Rp {{ $number($insights->totalTarget) }}</div>
                <div class="insight-kpi-note">Gabungan semua lini</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="insight-kpi-card">
                <div class="insight-kpi-label">Realisasi MTD</div>
                <div class="insight-kpi-value">Rp {{ $number($insights->totalMtd) }}</div>
                <div class="insight-kpi-note">{{ $percent($report->totalRow->achievementPercentage) }} dari target</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="insight-kpi-card {{ $insights->remainingTarget > 0 ? 'is-danger' : 'is-success' }}">
                <div class="insight-kpi-label">Sisa Target</div>
                <div class="insight-kpi-value">Rp {{ $number($insights->remainingTarget) }}</div>
                <div class="insight-kpi-note">Sisa {{ $filter->remainingSalesDays }} hari penjualan</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="insight-kpi-card {{ ($insights->forecastPercentage ?? 0) >= 1 ? 'is-success' : 'is-warning' }}">
                <div class="insight-kpi-label">Forecast EOM</div>
                <div class="insight-kpi-value">Rp {{ $number($insights->forecastEom) }}</div>
                <div class="insight-kpi-note">{{ $percent($insights->forecastPercentage) }} dari target</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="insight-kpi-card is-success">
                <div class="insight-kpi-label">On Track</div>
                <div class="insight-kpi-value">{{ $insights->onTrackCount }} lini</div>
                <div class="insight-kpi-note">Tercapai, ahead, atau on track</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="insight-kpi-card {{ $insights->atRiskCount > 0 ? 'is-danger' : 'is-success' }}">
                <div class="insight-kpi-label">At Risk</div>
                <div class="insight-kpi-value">{{ $insights->atRiskCount }} lini</div>
                <div class="insight-kpi-note">{{ $insights->targetMissingCount }} target belum diatur</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xxl-7">
            <div class="insight-panel h-100">
                <div class="insight-panel-head">
                    <div>
                        <h3 class="insight-title">Pace Penjualan per Lini</h3>
                        <div class="insight-subtitle">Berapa kebutuhan penjualan harian dan risiko gap pada akhir bulan.</div>
                    </div>
                </div>
                <div class="table-responsive insight-table-wrap">
                    <table class="table table-sm align-middle mb-0 insight-table">
                        <thead>
                        <tr>
                            <th>LiniPenjualan</th>
                            <th class="text-end">Achievement</th>
                            <th class="text-end">Sisa Target</th>
                            <th class="text-end">Required / Hari</th>
                            <th class="text-end">Rata-rata / Hari</th>
                            <th class="text-end">Forecast EOM</th>
                            <th class="text-end">Risk Gap</th>
                            <th class="text-center">Pace</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($insights->linePaces as $pace)
                            <tr>
                                <td class="fw-bold">{{ $pace['line'] }}</td>
                                <td class="text-end">{{ $percent($pace['achievement_percentage']) }}</td>
                                <td class="text-end">{{ $number($pace['remaining_target']) }}</td>
                                <td class="text-end">{{ $number($pace['required_daily_sales']) }}</td>
                                <td class="text-end">{{ $number($pace['average_daily_sales']) }}</td>
                                <td class="text-end">{{ $number($pace['estimated_eom']) }}</td>
                                <td class="text-end {{ $pace['forecast_gap'] > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' }}">{{ $number($pace['forecast_gap']) }}</td>
                                <td class="text-center"><span class="pace-badge pace-{{ $pace['pace_class'] }}">{{ $pace['pace_status'] }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-5">
            <div class="insight-panel h-100">
                <div class="insight-panel-head">
                    <div>
                        <h3 class="insight-title">Perlu Tindakan</h3>
                        <div class="insight-subtitle">Masalah otomatis diurutkan berdasarkan prioritas dan potensi gap.</div>
                    </div>
                </div>
                @forelse ($insights->actions as $action)
                    <article class="action-item">
                        <div class="d-flex justify-content-between gap-2 align-items-start">
                            <div class="fw-bold">{{ $action['line'] }}</div>
                            <span class="action-priority priority-{{ strtolower($action['priority']) }}">{{ $action['priority'] }}</span>
                        </div>
                        <div class="action-issue">{{ $action['issue'] }}</div>
                        <div class="action-impact">Potensi gap EOM: <strong>Rp {{ $number($action['impact']) }}</strong></div>
                        <div class="action-suggestion">{{ $action['suggestion'] }}</div>
                    </article>
                @empty
                    <div class="insight-empty">Tidak ada peringatan utama. Seluruh lini dengan target berada pada pace yang sehat.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-4">
            <div class="chart-panel insight-chart-panel">
                <div class="chart-title">Achievement per Lini</div>
                <div class="chart-subtitle">Merah &lt;70%, kuning 70–100%, hijau &gt;100%.</div>
                <canvas id="insightAchievementChart" aria-label="Grafik achievement per lini"></canvas>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="chart-panel insight-chart-panel">
                <div class="chart-title">Gap Terbesar ke Target</div>
                <div class="chart-subtitle">Urutan lini dengan sisa target rupiah terbesar.</div>
                <canvas id="insightGapChart" aria-label="Grafik gap target per lini"></canvas>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="chart-panel insight-chart-panel">
                <div class="chart-title">Cumulative MTD vs Target Pace</div>
                <div class="chart-subtitle">Akumulasi omzet aktual dibanding target kumulatif hari penjualan.</div>
                <canvas id="insightDailyTrendChart" aria-label="Grafik kumulatif penjualan"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="insight-panel h-100">
                <div class="insight-panel-head">
                    <div>
                        <h3 class="insight-title">Top &amp; Bottom Lini</h3>
                        <div class="insight-subtitle">Peringkat achievement dan growth MTD.</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <h4 class="rank-title">Achievement Terbaik</h4>
                        @foreach ($insights->topAchievement as $rank)
                            <div class="rank-row"><span>{{ $rank['line'] }}</span><strong>{{ $percent($rank['value']) }}</strong></div>
                        @endforeach
                    </div>
                    <div class="col-12 col-md-6">
                        <h4 class="rank-title">Achievement Terendah</h4>
                        @foreach ($insights->bottomAchievement as $rank)
                            <div class="rank-row"><span>{{ $rank['line'] }}</span><strong>{{ $percent($rank['value']) }}</strong></div>
                        @endforeach
                    </div>
                    <div class="col-12 col-md-6">
                        <h4 class="rank-title">Growth Terbaik</h4>
                        @forelse ($insights->topGrowth as $rank)
                            <div class="rank-row"><span>{{ $rank['line'] }}</span><strong class="text-success">{{ $percent($rank['value']) }}</strong></div>
                        @empty
                            <div class="small text-secondary">Belum ada pembanding LMTD.</div>
                        @endforelse
                    </div>
                    <div class="col-12 col-md-6">
                        <h4 class="rank-title">Penurunan Terbesar</h4>
                        @forelse ($insights->bottomGrowth as $rank)
                            <div class="rank-row"><span>{{ $rank['line'] }}</span><strong class="{{ $rank['value'] < 0 ? 'text-danger' : 'text-success' }}">{{ $percent($rank['value']) }}</strong></div>
                        @empty
                            <div class="small text-secondary">Belum ada pembanding LMTD.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="insight-panel h-100">
                <div class="insight-panel-head">
                    <div>
                        <h3 class="insight-title">Customer Concentration Risk</h3>
                        <div class="insight-subtitle">Ketergantungan omzet pada lima customer terbesar.</div>
                    </div>
                    <div class="concentration-value {{ ($insights->topFiveCustomerConcentration ?? 0) >= .60 ? 'text-danger' : 'text-success' }}">
                        {{ $percent($insights->topFiveCustomerConcentration) }}
                    </div>
                </div>
                <div class="concentration-track"><span style="width: {{ min(100, max(0, ($insights->topFiveCustomerConcentration ?? 0) * 100)) }}%"></span></div>
                <div class="small text-secondary mb-3">Semakin tinggi nilainya, semakin besar risiko penjualan bergantung pada sedikit customer.</div>
                @forelse (array_slice($insights->topCustomers, 0, 5) as $customer)
                    <div class="entity-row">
                        <div class="entity-label">{{ $customer['label'] }}</div>
                        <div class="entity-sales">Rp {{ $number($customer['sales']) }}</div>
                        <div class="entity-percent">{{ $percent($customer['percentage']) }}</div>
                    </div>
                @empty
                    <div class="insight-empty">Data customer belum tersedia pada hasil function.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="insight-panel h-100">
                <div class="insight-panel-head"><h3 class="insight-title">Top Salesperson</h3></div>
                @forelse (array_slice($insights->topSalespersons, 0, 7) as $entity)
                    <div class="entity-row"><div class="entity-label">{{ $entity['label'] }}</div><div class="entity-sales">Rp {{ $number($entity['sales']) }}</div><div class="entity-percent">{{ $percent($entity['percentage']) }}</div></div>
                @empty
                    <div class="insight-empty">Data salesperson belum tersedia.</div>
                @endforelse
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="insight-panel h-100">
                <div class="insight-panel-head"><h3 class="insight-title">Top Item</h3></div>
                @forelse (array_slice($insights->topItems, 0, 7) as $entity)
                    <div class="entity-row"><div class="entity-label">{{ $entity['label'] }}</div><div class="entity-sales">Rp {{ $number($entity['sales']) }}</div><div class="entity-percent">{{ $percent($entity['percentage']) }}</div></div>
                @empty
                    <div class="insight-empty">Data item belum tersedia.</div>
                @endforelse
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="insight-panel h-100">
                <div class="insight-panel-head"><h3 class="insight-title">Product Mix</h3></div>
                <h4 class="rank-title">Kontribusi Grade</h4>
                @foreach ($insights->gradeMix as $mix)
                    <div class="mix-row"><span>{{ $mix['label'] }}</span><strong>Rp {{ $number($mix['sales']) }}</strong><em>{{ $percent($mix['percentage']) }}</em></div>
                @endforeach
                <h4 class="rank-title mt-3">Status Item</h4>
                @foreach ($insights->statusMix as $mix)
                    <div class="mix-row"><span>{{ $mix['label'] }}</span><strong>Rp {{ $number($mix['sales']) }}</strong><em>{{ $percent($mix['percentage']) }}</em></div>
                @endforeach
            </div>
        </div>
    </div>
</section>
