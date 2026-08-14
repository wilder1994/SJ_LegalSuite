/**
 * Dashboard disciplinario: donas por etapa, mapa Colombia y gráfica por tipo de falta.
 */

function chartForeColor(dark) {
    return dark ? '#94a3b8' : '#64748b';
}

function mountApex(el, opts, store, key) {
    if (!el || !window.ApexCharts) {
        return null;
    }

    if (key && store[key]) {
        try {
            store[key].destroy();
        } catch {
            //
        }
        delete store[key];
    }

    const chart = new window.ApexCharts(el, opts);
    chart.render();
    el._apexChart = chart;
    if (key) {
        store[key] = chart;
    }

    requestAnimationFrame(() => {
        try {
            chart.resize();
            // resize puede recrear el nodo; recentrar después.
            const canvas = el.querySelector('.apexcharts-canvas');
            if (canvas instanceof HTMLElement) {
                canvas.style.marginLeft = 'auto';
                canvas.style.marginRight = 'auto';
            }
        } catch {
            //
        }
    });

    return chart;
}

function destroyCharts(store) {
    Object.keys(store).forEach((key) => {
        try {
            store[key]?.destroy();
        } catch {
            //
        }
        delete store[key];
    });
}

function waitForWidth(el, mountFn, maxTries = 72) {
    let tries = 0;
    const attempt = () => {
        tries++;
        if (!el || !el.isConnected) {
            if (tries < maxTries) {
                requestAnimationFrame(attempt);
            }
            return;
        }
        // Usar el ancho real de la celda (sin piso 96px): si el canvas es más ancho que la columna,
        // Apex lo deja a la izquierda y el título centrado queda desalineado.
        const w = Math.floor(Math.max(el.offsetWidth || 0, el.getBoundingClientRect?.().width || 0));
        if (w < 40 && tries < maxTries) {
            requestAnimationFrame(attempt);
            return;
        }
        mountFn(Math.max(40, w));
    };
    requestAnimationFrame(() => requestAnimationFrame(attempt));
}

function donutChartHeight() {
    return typeof window !== 'undefined' && window.matchMedia('(min-width: 1280px)').matches ? 112 : 100;
}

function baseDonutOptions(chartDark, chartW, chartH, fg, hair) {
    return {
        chart: {
            type: 'donut',
            height: chartH,
            width: chartW,
            offsetY: 0,
            parentHeightOffset: 0,
            fontFamily: 'Figtree, ui-sans-serif, system-ui',
            foreColor: fg,
            background: 'transparent',
        },
        theme: { mode: chartDark ? 'dark' : 'light' },
        stroke: { width: 1, colors: [hair, hair] },
        legend: { show: false },
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                offsetY: -2,
                customScale: 0.92,
                expandOnClick: false,
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            color: chartDark ? '#cbd5e1' : '#64748b',
                            fontSize: '10px',
                            fontWeight: 600,
                            offsetY: -2,
                        },
                        value: {
                            show: true,
                            color: chartDark ? '#f8fafc' : '#0f172a',
                            fontSize: '15px',
                            fontWeight: 700,
                            offsetY: 1,
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            color: chartDark ? '#cbd5e1' : '#64748b',
                            fontSize: '10px',
                            fontWeight: 600,
                        },
                    },
                },
            },
        },
        tooltip: {
            theme: chartDark ? 'dark' : 'light',
            y: { formatter: (val) => `${val} caso(s)` },
        },
    };
}

function safeDonutSeries(active, rest = 0) {
    const a = Math.max(0, Number(active) || 0);
    const r = Math.max(0, Number(rest) || 0);
    if (a + r <= 0) {
        // Apex no pinta bien [0,0]; anillo vacío con track neutro.
        return [0, 1];
    }

    return [a, r];
}

function mountTotalDonut(el, config, store) {
    const chartDark = config.chartDark;
    const wTotal = Number(config.workflow?.total ?? 0);
    const totalNeon = { from: '#fcd34d', to: '#b45309', shadow: '#fcd34d' };
    const emptyTrack = chartDark ? 'rgba(51,65,85,0.55)' : '#e2e8f0';
    const emptyTrackTo = chartDark ? 'rgba(30,41,59,0.85)' : '#cbd5e1';
    const fg = chartForeColor(chartDark);
    const hair = chartDark ? 'rgba(15,23,42,0.28)' : 'rgba(255,255,255,0.55)';
    const chartH = donutChartHeight();
    const empty = wTotal <= 0;

    waitForWidth(el, (chartW) => {
        const opts = baseDonutOptions(chartDark, chartW, chartH, fg, hair);
        opts.chart.dropShadow = chartDark && ! empty
            ? { enabled: true, top: 3, blur: 10, opacity: 0.32, color: totalNeon.shadow }
            : { enabled: false };
        opts.labels = ['Casos', ''];
        opts.series = safeDonutSeries(wTotal, 0);
        opts.colors = empty ? [emptyTrack, emptyTrack] : [totalNeon.from, totalNeon.from];
        opts.fill = {
            type: 'gradient',
            gradient: {
                shade: 'dark',
                type: 'horizontal',
                shadeIntensity: chartDark ? 0.72 : 0.55,
                opacityFrom: 1,
                opacityTo: chartDark ? 0.92 : 0.92,
                gradientToColors: empty ? [emptyTrackTo, emptyTrackTo] : [totalNeon.to, totalNeon.to],
            },
        };
        opts.plotOptions.pie.donut.labels.total.label = empty ? '0%' : '100%';
        opts.plotOptions.pie.donut.labels.total.formatter = () => String(wTotal);
        mountApex(el, opts, store, 'donut-total');
    });
}

function mountStageDonut(el, stage, config, store) {
    const chartDark = config.chartDark;
    const palette = config.stagePalette?.[stage.letter] ?? { from: '#818cf8', to: '#4338ca', shadow: '#818cf8' };
    const fg = chartForeColor(chartDark);
    const hair = chartDark ? 'rgba(15,23,42,0.28)' : 'rgba(255,255,255,0.55)';
    const restFill = chartDark ? 'rgba(51,65,85,0.55)' : '#e2e8f0';
    const restFillTo = chartDark ? 'rgba(30,41,59,0.85)' : '#cbd5e1';
    const chartH = donutChartHeight();
    const active = Number(stage.count ?? 0);
    const rest = Number(stage.rest ?? 0);
    const pct = `${stage.percent_label ?? '0'}%`;
    const empty = active + rest <= 0;

    waitForWidth(el, (chartW) => {
        const opts = baseDonutOptions(chartDark, chartW, chartH, fg, hair);
        opts.chart.dropShadow = chartDark && ! empty
            ? { enabled: true, top: 3, blur: 10, opacity: 0.32, color: palette.shadow }
            : { enabled: false };
        opts.labels = ['En etapa', 'Resto'];
        opts.series = safeDonutSeries(active, rest);
        opts.colors = [empty ? restFill : palette.from, restFill];
        opts.fill = {
            type: 'gradient',
            gradient: {
                shade: 'dark',
                type: 'horizontal',
                shadeIntensity: chartDark ? 0.72 : 0.55,
                opacityFrom: 1,
                opacityTo: chartDark ? 0.92 : 0.92,
                gradientToColors: [empty ? restFillTo : palette.to, restFillTo],
            },
        };
        opts.plotOptions.pie.donut.labels.total.label = pct;
        opts.plotOptions.pie.donut.labels.total.formatter = () => String(active);
        mountApex(el, opts, store, `donut-${stage.letter}`);
    });
}

export function disciplinaryDashboard(config) {
    return {
        chartDark: Boolean(config.chartDark),
        charts: {},
        highlightedMunicipality: null,

        init() {
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    this.mountWorkflowDonuts();
                });
            });
        },

        destroy() {
            destroyCharts(this.charts);
        },

        mountWorkflowDonuts() {
            const root = this.$refs.workflowDonuts;
            if (!root || !config.workflow?.stages) {
                return;
            }

            const totalEl = root.querySelector('[data-workflow-donut="total"]');
            if (totalEl) {
                mountTotalDonut(totalEl, config, this.charts);
            }

            config.workflow.stages.forEach((stage) => {
                const el = root.querySelector(`[data-workflow-donut="${stage.letter}"]`);
                if (el) {
                    mountStageDonut(el, stage, config, this.charts);
                }
            });
        },

        focusMunicipality(code) {
            const el = document.getElementById('disciplinary-colombia-map');
            const map = el?.__disciplinaryColombiaLeafletMap;
            const marker = el?.__disciplinaryColombiaMapMarkersByCode?.[String(code)];
            if (!map || !marker) {
                return;
            }

            this.highlightedMunicipality = String(code);
            map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 8), { duration: 0.65 });
            marker.openTooltip();
        },
    };
}

window.disciplinaryDashboard = disciplinaryDashboard;

export default disciplinaryDashboard;
