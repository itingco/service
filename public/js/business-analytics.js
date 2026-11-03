(function () {
    const data = window.businessAnalyticsChartData;
    const canvas = document.getElementById('businessAnalyticsTrendChart');
    if (!data || !canvas || typeof Chart === 'undefined') return;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Actual',
                    data: data.actual,
                    borderWidth: 2.5,
                    pointRadius: 2,
                    tension: 0.25,
                    spanGaps: false
                },
                {
                    label: 'Forecast',
                    data: data.forecast,
                    borderWidth: 2.5,
                    borderDash: [7, 5],
                    pointRadius: 3,
                    tension: 0.25,
                    spanGaps: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    ticks: {
                        callback: function (value) {
                            const n = Number(value || 0);
                            if (Math.abs(n) >= 1e9) return 'Rp ' + (n / 1e9).toFixed(1) + ' M';
                            if (Math.abs(n) >= 1e6) return 'Rp ' + (n / 1e6).toFixed(0) + ' Jt';
                            return 'Rp ' + n.toLocaleString('id-ID');
                        }
                    }
                }
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            if (ctx.raw == null) return '';
                            return ctx.dataset.label + ': Rp ' + Number(ctx.raw).toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
})();
