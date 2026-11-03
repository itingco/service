(() => {
    'use strict';

    const data = window.salesDashboardData;
    if (!data || !Array.isArray(data.labels)) return;

    const compact = new Intl.NumberFormat('id-ID', {
        notation: 'compact',
        maximumFractionDigits: 1,
    });

    const colors = {
        yellow: '#ffc000',
        blue: '#4472c4',
        gray: '#d0d5dd',
        dark: '#344054',
        grid: '#eaecf0',
        red: '#c00000',
    };

    function setup(canvas, cssHeight) {
        const parentWidth = Math.max(320, canvas.parentElement.clientWidth - 2);
        const dpr = Math.max(1, window.devicePixelRatio || 1);
        canvas.style.height = `${cssHeight}px`;
        canvas.width = Math.round(parentWidth * dpr);
        canvas.height = Math.round(cssHeight * dpr);
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx, width: parentWidth, height: cssHeight };
    }

    function ellipsis(text, max) {
        return text.length > max ? `${text.slice(0, max - 1)}…` : text;
    }

    function legend(ctx, items, x, y) {
        ctx.font = '12px Arial';
        let cursor = x;
        items.forEach((item) => {
            ctx.fillStyle = item.color;
            ctx.fillRect(cursor, y - 9, 12, 8);
            ctx.fillStyle = colors.dark;
            ctx.fillText(item.label, cursor + 17, y);
            cursor += ctx.measureText(item.label).width + 48;
        });
    }

    function horizontalBars(canvas, seriesA, seriesB, labelA, labelB, colorA, colorB) {
        const labels = data.labels;
        const rowHeight = 31;
        const height = Math.max(360, labels.length * rowHeight + 72);
        const { ctx, width } = setup(canvas, height);
        const left = Math.min(190, Math.max(125, width * .31));
        const right = 24;
        const top = 42;
        const plotWidth = Math.max(80, width - left - right);
        const max = Math.max(1, ...seriesA, ...seriesB);

        ctx.clearRect(0, 0, width, height);
        legend(ctx, [
            { label: labelA, color: colorA },
            { label: labelB, color: colorB },
        ], left, 20);

        labels.forEach((label, index) => {
            const y = top + index * rowHeight;
            const aWidth = (Math.max(0, seriesA[index]) / max) * plotWidth;
            const bWidth = (Math.max(0, seriesB[index]) / max) * plotWidth;

            ctx.strokeStyle = colors.grid;
            ctx.beginPath();
            ctx.moveTo(left, y + 23);
            ctx.lineTo(width - right, y + 23);
            ctx.stroke();

            ctx.fillStyle = colors.dark;
            ctx.font = '11px Arial';
            ctx.textAlign = 'right';
            ctx.fillText(ellipsis(label, width < 700 ? 20 : 28), left - 8, y + 15);

            ctx.fillStyle = colorA;
            ctx.fillRect(left, y + 3, aWidth, 8);
            ctx.fillStyle = colorB;
            ctx.fillRect(left, y + 13, bWidth, 8);

            ctx.textAlign = 'left';
            ctx.font = '10px Arial';
            if (aWidth > 4) {
                ctx.fillStyle = colors.dark;
                ctx.fillText(compact.format(seriesA[index]), Math.min(width - 52, left + aWidth + 5), y + 10);
            }
            if (bWidth > 4) {
                ctx.fillStyle = colors.dark;
                ctx.fillText(compact.format(seriesB[index]), Math.min(width - 52, left + bWidth + 5), y + 21);
            }
        });
    }

    const targetCanvas = document.getElementById('salesTargetChart');
    const comparisonCanvas = document.getElementById('salesComparisonChart');

    function render() {
        if (targetCanvas) {
            horizontalBars(
                targetCanvas,
                data.targets.map(Number),
                data.mtd.map(Number),
                'Target',
                'MTD',
                colors.gray,
                colors.yellow,
            );
        }
        if (comparisonCanvas) {
            horizontalBars(
                comparisonCanvas,
                data.lmtd.map(Number),
                data.mtd.map(Number),
                'LMTD',
                'MTD',
                colors.blue,
                colors.yellow,
            );
        }
    }

    let timer;
    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(render, 80);
    };

    render();
    window.addEventListener('resize', schedule, { passive: true });
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(schedule);
        if (targetCanvas) observer.observe(targetCanvas.parentElement);
        if (comparisonCanvas) observer.observe(comparisonCanvas.parentElement);
    }
})();
