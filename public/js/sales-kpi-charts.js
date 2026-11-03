(() => {
    'use strict';

    const data = window.salesKpiData;
    if (!data || !Array.isArray(data.labels) || data.labels.length === 0) return;

    const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
    const decimal = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const percent = new Intl.NumberFormat('id-ID', { style: 'percent', minimumFractionDigits: 0, maximumFractionDigits: 1 });

    const palette = {
        dark: '#101828', text: '#344054', muted: '#667085', grid: '#eaecf0',
        yellow: '#ffc000', orange: '#f79009', blue: '#4472c4', green: '#12b76a',
        red: '#f04438', purple: '#7f56d9', gray: '#98a2b3', light: '#d0d5dd',
        cyan: '#06aed4', pink: '#ee46bc', indigo: '#6172f3', teal: '#15b79e',
    };
    const pieColors = [palette.yellow, palette.blue, palette.green, palette.orange, palette.purple, palette.red, palette.cyan, palette.pink, palette.indigo, palette.teal, palette.gray];

    function setup(canvas, cssHeight, minWidth = 360) {
        const parentWidth = Math.max(360, (canvas.parentElement?.clientWidth || 900) - 2);
        const width = Math.max(parentWidth, minWidth);
        const dpr = Math.max(1, window.devicePixelRatio || 1);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${cssHeight}px`;
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(cssHeight * dpr);
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx, width, height: cssHeight };
    }

    function truncate(value, max) {
        const text = String(value ?? '');
        return text.length > max ? `${text.slice(0, max - 1)}…` : text;
    }

    function drawLegend(ctx, items, x, y, width) {
        ctx.font = '11px Arial';
        let cursorX = x;
        let cursorY = y;
        items.forEach((item) => {
            const itemWidth = ctx.measureText(item.label).width + 38;
            if (cursorX + itemWidth > width - 10) {
                cursorX = x;
                cursorY += 18;
            }
            ctx.fillStyle = item.color;
            ctx.fillRect(cursorX, cursorY - 8, 11, 7);
            ctx.fillStyle = palette.text;
            ctx.fillText(item.label, cursorX + 16, cursorY);
            cursorX += itemWidth;
        });
        return cursorY;
    }

    function verticalGrouped(canvas, options) {
        if (!canvas) return;
        const labels = data.labels;
        const series = options.series;
        const groupWidth = Math.max(76, series.length * 17 + 36);
        const minWidth = 95 + labels.length * groupWidth;
        const height = options.height || 360;
        const { ctx, width } = setup(canvas, height, minWidth);
        const left = 72;
        const right = 24;
        let top = 34;
        const bottom = 92;
        const plotWidth = width - left - right;
        const plotHeight = height - top - bottom;
        const observed = series.flatMap((item) => item.values.map(Number).filter(Number.isFinite));
        const max = Math.max(options.max ?? 0, ...observed, 1);
        const ticks = options.ticks || 5;

        ctx.clearRect(0, 0, width, height);
        top = drawLegend(ctx, series, left, 16, width) + 24;
        const actualPlotHeight = height - top - bottom;

        ctx.font = '10px Arial';
        ctx.textAlign = 'right';
        for (let i = 0; i <= ticks; i += 1) {
            const ratio = i / ticks;
            const value = max * ratio;
            const y = top + actualPlotHeight - actualPlotHeight * ratio;
            ctx.strokeStyle = palette.grid;
            ctx.beginPath();
            ctx.moveTo(left, y);
            ctx.lineTo(width - right, y);
            ctx.stroke();
            ctx.fillStyle = palette.muted;
            ctx.fillText((options.axisFormatter || decimal.format.bind(decimal))(value), left - 8, y + 3);
        }

        (options.horizontalLines || []).forEach((line) => {
            const y = top + actualPlotHeight - Math.min(1, Math.max(0, line.value / max)) * actualPlotHeight;
            ctx.strokeStyle = line.color || palette.red;
            ctx.setLineDash([5, 4]);
            ctx.beginPath();
            ctx.moveTo(left, y);
            ctx.lineTo(width - right, y);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.fillStyle = line.color || palette.red;
            ctx.textAlign = 'left';
            ctx.fillText(line.label, left + 4, y - 4);
        });

        const slot = plotWidth / labels.length;
        const barWidth = Math.min(20, Math.max(8, (slot - 18) / series.length));
        labels.forEach((label, index) => {
            const centerX = left + slot * index + slot / 2;
            const totalBarsWidth = barWidth * series.length + Math.max(0, series.length - 1) * 3;
            let x = centerX - totalBarsWidth / 2;

            series.forEach((item) => {
                const raw = item.values[index];
                const numeric = Number(raw);
                if (raw !== null && raw !== undefined && Number.isFinite(numeric)) {
                    const barHeight = Math.max(0, Math.min(actualPlotHeight, (numeric / max) * actualPlotHeight));
                    ctx.fillStyle = item.color;
                    ctx.fillRect(x, top + actualPlotHeight - barHeight, barWidth, barHeight);
                }
                x += barWidth + 3;
            });

            ctx.save();
            ctx.translate(centerX + 3, top + actualPlotHeight + 12);
            ctx.rotate(-Math.PI / 4);
            ctx.fillStyle = palette.text;
            ctx.font = '10px Arial';
            ctx.textAlign = 'right';
            ctx.fillText(truncate(label, 24), 0, 0);
            ctx.restore();
        });
    }

    function drawContributionPie(canvas) {
        if (!canvas) return;
        const raw = data.labels.map((label, index) => ({ label, value: Math.max(0, Number(data.current_omzet[index] || 0)) }))
            .filter((item) => item.value > 0)
            .sort((a, b) => b.value - a.value);
        if (raw.length === 0) return;

        const top = raw.slice(0, 9);
        if (raw.length > 9) {
            top.push({ label: 'LAINNYA', value: raw.slice(9).reduce((sum, item) => sum + item.value, 0) });
        }
        const total = top.reduce((sum, item) => sum + item.value, 0);
        const { ctx, width, height } = setup(canvas, 330, 430);
        const radius = Math.min(112, height * .34, width * .28);
        const centerX = Math.min(width * .34, 150);
        const centerY = 160;
        let angle = -Math.PI / 2;

        ctx.clearRect(0, 0, width, height);
        top.forEach((item, index) => {
            const slice = total === 0 ? 0 : (item.value / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, angle, angle + slice);
            ctx.closePath();
            ctx.fillStyle = pieColors[index % pieColors.length];
            ctx.fill();
            angle += slice;
        });

        ctx.beginPath();
        ctx.arc(centerX, centerY, radius * .54, 0, Math.PI * 2);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.fillStyle = palette.dark;
        ctx.font = 'bold 13px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Total Omzet', centerX, centerY - 5);
        ctx.font = 'bold 12px Arial';
        ctx.fillText(compact.format(total), centerX, centerY + 16);

        const legendX = Math.max(centerX + radius + 28, width * .56);
        let y = 56;
        top.forEach((item, index) => {
            ctx.fillStyle = pieColors[index % pieColors.length];
            ctx.fillRect(legendX, y - 9, 12, 9);
            ctx.fillStyle = palette.text;
            ctx.font = '11px Arial';
            ctx.textAlign = 'left';
            const share = total === 0 ? 0 : item.value / total;
            ctx.fillText(`${truncate(item.label, 22)} — ${percent.format(share)}`, legendX + 18, y);
            y += 25;
        });
    }

    function render() {
        drawContributionPie(document.getElementById('kpiContributionPieChart'));

        verticalGrouped(document.getElementById('kpiSummaryBarChart'), {
            max: 90,
            axisFormatter: (value) => decimal.format(value),
            series: [{ label: 'Skor Akhir / 90', values: data.overall_scores, color: palette.yellow }],
            horizontalLines: [
                { value: 60, label: 'Batas Perlu Perhatian 60', color: palette.orange },
                { value: 80, label: 'Batas Baik 80', color: palette.green },
            ],
        });

        verticalGrouped(document.getElementById('targetKpiChart'), {
            axisFormatter: compact.format.bind(compact),
            series: [
                { label: 'Target', values: data.targets, color: palette.light },
                { label: 'Batas 70%', values: data.threshold_70, color: palette.orange },
                { label: 'Omzet Aktual', values: data.current_omzet, color: palette.yellow },
            ],
        });

        verticalGrouped(document.getElementById('growthKpiChart'), {
            axisFormatter: compact.format.bind(compact),
            series: [
                { label: 'Periode Sebelumnya', values: data.growth_previous, color: palette.blue },
                { label: 'Periode Sekarang', values: data.growth_current, color: palette.yellow },
            ],
        });

        verticalGrouped(document.getElementById('riskKpiChart'), {
            axisFormatter: compact.format.bind(compact),
            series: [
                { label: 'Discontinued', values: data.discontinued, color: palette.red },
                { label: 'Item Reject', values: data.clearance, color: palette.orange },
                { label: 'Target 9%', values: data.risk_targets, color: palette.gray },
            ],
        });

        verticalGrouped(document.getElementById('gradeKpiChart'), {
            max: Math.max(.35, ...data.grade_a_contribution.map(Number).filter(Number.isFinite), ...data.grade_b_contribution.map(Number).filter(Number.isFinite)),
            axisFormatter: (value) => percent.format(value),
            series: [
                { label: 'Kontribusi Grade A (target 35%)', values: data.grade_a_contribution, color: palette.purple },
                { label: 'Kontribusi Grade B (target 20%)', values: data.grade_b_contribution, color: palette.green },
            ],
        });
    }

    let timer;
    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(render, 100);
    }

    render();
    window.addEventListener('resize', schedule, { passive: true });
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => tab.addEventListener('shown.bs.tab', schedule));
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(schedule);
        document.querySelectorAll('.kpi-chart-wrap').forEach((element) => observer.observe(element));
    }
})();
