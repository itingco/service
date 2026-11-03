(() => {
    'use strict';

    const data = window.salesDashboardInsightData;
    if (!data || !Array.isArray(data.line_labels)) return;

    const compact = new Intl.NumberFormat('id-ID', {
        notation: 'compact',
        maximumFractionDigits: 1,
    });

    const colors = {
        red: '#dc3545',
        yellow: '#facc15',
        green: '#15803d',
        blue: '#4472c4',
        navy: '#17365d',
        gray: '#d0d5dd',
        grid: '#eaecf0',
        text: '#344054',
    };

    function setup(canvas, height) {
        if (!canvas) return null;
        const width = Math.max(320, canvas.parentElement.clientWidth - 2);
        const dpr = Math.max(1, window.devicePixelRatio || 1);
        canvas.style.height = `${height}px`;
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);
        return { ctx, width, height };
    }

    function shortened(text, max) {
        const value = String(text || '');
        return value.length > max ? `${value.slice(0, max - 1)}…` : value;
    }

    function achievementColor(value) {
        if (value === null || Number.isNaN(Number(value))) return colors.gray;
        if (Number(value) < 70) return colors.red;
        if (Number(value) <= 100) return colors.yellow;
        return colors.green;
    }

    function renderAchievement() {
        const canvas = document.getElementById('insightAchievementChart');
        const labels = data.line_labels;
        const values = data.achievement.map((value) => value === null ? null : Number(value));
        const rowHeight = 27;
        const height = Math.max(320, labels.length * rowHeight + 48);
        const setupData = setup(canvas, height);
        if (!setupData) return;
        const { ctx, width } = setupData;
        const left = Math.min(170, Math.max(110, width * .35));
        const right = 38;
        const top = 20;
        const plotWidth = Math.max(80, width - left - right);
        const maxValue = Math.max(110, ...values.filter((value) => value !== null));

        labels.forEach((label, index) => {
            const y = top + index * rowHeight;
            const value = values[index];
            ctx.font = '11px Arial';
            ctx.textAlign = 'right';
            ctx.fillStyle = colors.text;
            ctx.fillText(shortened(label, width < 540 ? 18 : 25), left - 8, y + 13);

            ctx.fillStyle = '#f2f4f7';
            ctx.fillRect(left, y + 3, plotWidth, 13);
            if (value !== null) {
                const barWidth = Math.min(plotWidth, Math.max(0, value) / maxValue * plotWidth);
                ctx.fillStyle = achievementColor(value);
                ctx.fillRect(left, y + 3, barWidth, 13);
                ctx.textAlign = 'left';
                ctx.fillStyle = colors.text;
                ctx.fillText(`${value.toFixed(1)}%`, Math.min(width - 38, left + barWidth + 5), y + 13);
            } else {
                ctx.textAlign = 'left';
                ctx.fillStyle = '#667085';
                ctx.fillText('N/A', left + 5, y + 13);
            }
        });
    }

    function renderGap() {
        const canvas = document.getElementById('insightGapChart');
        const combined = data.line_labels.map((label, index) => ({
            label,
            value: Math.max(0, Number(data.gaps[index] || 0)),
        })).sort((a, b) => b.value - a.value);
        const rowHeight = 27;
        const height = Math.max(320, combined.length * rowHeight + 48);
        const setupData = setup(canvas, height);
        if (!setupData) return;
        const { ctx, width } = setupData;
        const left = Math.min(170, Math.max(110, width * .35));
        const right = 62;
        const top = 20;
        const plotWidth = Math.max(70, width - left - right);
        const maxValue = Math.max(1, ...combined.map((row) => row.value));

        combined.forEach((row, index) => {
            const y = top + index * rowHeight;
            const barWidth = row.value / maxValue * plotWidth;
            ctx.font = '11px Arial';
            ctx.textAlign = 'right';
            ctx.fillStyle = colors.text;
            ctx.fillText(shortened(row.label, width < 540 ? 18 : 25), left - 8, y + 13);
            ctx.fillStyle = '#f2f4f7';
            ctx.fillRect(left, y + 3, plotWidth, 13);
            ctx.fillStyle = row.value > 0 ? colors.red : colors.green;
            ctx.fillRect(left, y + 3, barWidth, 13);
            ctx.textAlign = 'left';
            ctx.fillStyle = colors.text;
            ctx.fillText(compact.format(row.value), Math.min(width - 56, left + barWidth + 5), y + 13);
        });
    }

    function renderTrend() {
        const canvas = document.getElementById('insightDailyTrendChart');
        const trend = data.daily_trend || {};
        const labels = trend.labels || [];
        const actual = (trend.actual_cumulative || []).map(Number);
        const target = (trend.target_cumulative || []).map(Number);
        const setupData = setup(canvas, 340);
        if (!setupData || labels.length === 0) return;
        const { ctx, width, height } = setupData;
        const left = 56;
        const right = 18;
        const top = 34;
        const bottom = 42;
        const plotWidth = width - left - right;
        const plotHeight = height - top - bottom;
        const maxValue = Math.max(1, ...actual, ...target);

        ctx.strokeStyle = colors.grid;
        ctx.fillStyle = colors.text;
        ctx.font = '10px Arial';
        for (let step = 0; step <= 4; step++) {
            const y = top + plotHeight * step / 4;
            const value = maxValue * (1 - step / 4);
            ctx.beginPath();
            ctx.moveTo(left, y);
            ctx.lineTo(width - right, y);
            ctx.stroke();
            ctx.textAlign = 'right';
            ctx.fillText(compact.format(value), left - 6, y + 3);
        }

        function drawLine(values, color) {
            ctx.strokeStyle = color;
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            values.forEach((value, index) => {
                const x = left + (labels.length === 1 ? 0 : index / (labels.length - 1) * plotWidth);
                const y = top + plotHeight - (value / maxValue * plotHeight);
                if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.stroke();
        }

        drawLine(target, colors.gray);
        drawLine(actual, colors.blue);

        ctx.textAlign = 'center';
        ctx.fillStyle = colors.text;
        const interval = Math.max(1, Math.ceil(labels.length / 6));
        labels.forEach((label, index) => {
            if (index % interval !== 0 && index !== labels.length - 1) return;
            const x = left + (labels.length === 1 ? 0 : index / (labels.length - 1) * plotWidth);
            ctx.fillText(label, x, height - 18);
        });

        ctx.fillStyle = colors.blue;
        ctx.fillRect(left, 12, 12, 8);
        ctx.fillStyle = colors.text;
        ctx.textAlign = 'left';
        ctx.fillText('Aktual Kumulatif', left + 18, 20);
        ctx.fillStyle = colors.gray;
        ctx.fillRect(left + 118, 12, 12, 8);
        ctx.fillStyle = colors.text;
        ctx.fillText('Target Pace', left + 136, 20);
    }

    function renderAll() {
        renderAchievement();
        renderGap();
        renderTrend();
    }

    let timer;
    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(renderAll, 80);
    };

    renderAll();
    window.addEventListener('resize', schedule, { passive: true });
})();
