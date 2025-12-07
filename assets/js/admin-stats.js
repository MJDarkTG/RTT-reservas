/**
 * RTT Reservas - Admin Statistics Charts
 */

(function($) {
    'use strict';

    // Configuración de colores
    const colors = {
        primary: '#3b82f6',
        success: '#2db742',
        warning: '#f59e0b',
        danger: '#ef4444',
        purple: '#8b5cf6',
        cyan: '#06b6d4',
        pink: '#ec4899',
        indigo: '#6366f1',
        teal: '#14b8a6',
        orange: '#f97316',
        palette: [
            '#3b82f6', '#2db742', '#f59e0b', '#8b5cf6', '#06b6d4',
            '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#ef4444',
            '#84cc16', '#22d3ee', '#a855f7', '#fb923c', '#4ade80'
        ]
    };

    // Instancias de gráficas
    let charts = {};

    // Inicializar
    $(document).ready(function() {
        loadStats();

        // Eventos
        $('#rtt-period').on('change', loadStats);
        $('#rtt-refresh-stats').on('click', loadStats);
    });

    /**
     * Cargar estadísticas vía AJAX
     */
    function loadStats() {
        const period = $('#rtt-period').val();

        $('#rtt-loading').removeClass('hidden');

        $.ajax({
            url: rttStats.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rtt_get_stats',
                nonce: rttStats.nonce,
                period: period
            },
            success: function(response) {
                if (response.success) {
                    renderStats(response.data);
                }
                $('#rtt-loading').addClass('hidden');
            },
            error: function() {
                $('#rtt-loading').addClass('hidden');
                alert('Error al cargar estadísticas');
            }
        });
    }

    /**
     * Renderizar todas las estadísticas
     */
    function renderStats(data) {
        // Tarjetas de resumen
        renderSummary(data.summary);

        // Gráficas
        renderTimelineChart(data.timeline);
        renderCountriesChart(data.byCountry);
        renderToursChart(data.byTour);
        renderStatusChart(data.byStatus);
        renderMonthlyChart(data.byMonth);

        // Tablas
        renderCountriesTable(data.byCountry, data.summary.totalReservas);
        renderRecentTable(data.recentReservations);
    }

    /**
     * Renderizar tarjetas de resumen
     */
    function renderSummary(summary) {
        $('#stat-total-reservas').text(formatNumber(summary.totalReservas));
        $('#stat-total-pasajeros').text(formatNumber(summary.totalPasajeros));
        $('#stat-pendientes').text(formatNumber(summary.pendientes));
        $('#stat-paises').text(formatNumber(summary.paisesUnicos));

        // Growth indicator
        const $growth = $('#stat-growth');
        if (summary.growth !== 0) {
            const icon = summary.growth > 0 ? '↑' : '↓';
            const className = summary.growth > 0 ? 'positive' : 'negative';
            $growth.html(icon + ' ' + Math.abs(summary.growth) + '%')
                   .removeClass('positive negative')
                   .addClass(className)
                   .show();
        } else {
            $growth.hide();
        }
    }

    /**
     * Gráfica de barras - Reservas por día
     */
    function renderTimelineChart(data) {
        const ctx = document.getElementById('chart-timeline');
        if (!ctx) return;

        if (charts.timeline) {
            charts.timeline.destroy();
        }

        // Si no hay datos
        if (!data || data.length === 0) {
            charts.timeline = new Chart(ctx, {
                type: 'bar',
                data: { labels: ['Sin datos'], datasets: [{ data: [0] }] },
                options: {
                    plugins: {
                        legend: { display: false },
                        title: {
                            display: true,
                            text: 'No hay reservas en este período',
                            color: '#94a3b8'
                        }
                    }
                }
            });
            return;
        }

        const labels = data.map(d => formatDateFull(d.fecha));
        const reservas = data.map(d => parseInt(d.total));
        const pasajeros = data.map(d => parseInt(d.pasajeros));

        // Calcular totales
        const totalReservas = reservas.reduce((a, b) => a + b, 0);
        const totalPasajeros = pasajeros.reduce((a, b) => a + b, 0);

        charts.timeline = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Reservas (' + totalReservas + ' total)',
                        data: reservas,
                        backgroundColor: colors.primary,
                        borderRadius: 6,
                        borderSkipped: false,
                        barThickness: data.length > 15 ? 'flex' : 20
                    },
                    {
                        label: 'Pasajeros (' + totalPasajeros + ' total)',
                        data: pasajeros,
                        backgroundColor: colors.success,
                        borderRadius: 6,
                        borderSkipped: false,
                        barThickness: data.length > 15 ? 'flex' : 20
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 20,
                            font: { size: 13, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 14,
                        cornerRadius: 10,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        callbacks: {
                            title: function(items) {
                                return '📅 ' + items[0].label;
                            },
                            label: function(context) {
                                const icon = context.datasetIndex === 0 ? '📋' : '👥';
                                return icon + ' ' + context.dataset.label.split(' (')[0] + ': ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 45,
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            stepSize: 1,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });
    }

    /**
     * Gráfica de países (Doughnut)
     */
    function renderCountriesChart(data) {
        const ctx = document.getElementById('chart-countries');
        if (!ctx) return;

        if (charts.countries) {
            charts.countries.destroy();
        }

        const top5 = data.slice(0, 8);
        const labels = top5.map(d => d.pais);
        const values = top5.map(d => parseInt(d.total));

        charts.countries = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.palette.slice(0, labels.length),
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Gráfica de tours (Barras horizontales)
     */
    function renderToursChart(data) {
        const ctx = document.getElementById('chart-tours');
        if (!ctx) return;

        if (charts.tours) {
            charts.tours.destroy();
        }

        const top5 = data.slice(0, 6);
        const labels = top5.map(d => truncateText(d.tour, 25));
        const values = top5.map(d => parseInt(d.total));

        charts.tours = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reservas',
                    data: values,
                    backgroundColor: createGradientArray(ctx, colors.success, colors.teal, values.length),
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /**
     * Gráfica de estados (Pie)
     */
    function renderStatusChart(data) {
        const ctx = document.getElementById('chart-status');
        if (!ctx) return;

        if (charts.status) {
            charts.status.destroy();
        }

        const statusColors = {
            'pendiente': colors.warning,
            'confirmada': colors.success,
            'cancelada': colors.danger
        };

        const statusLabels = {
            'pendiente': 'Pendiente',
            'confirmada': 'Confirmada',
            'cancelada': 'Cancelada'
        };

        const labels = data.map(d => statusLabels[d.estado] || d.estado);
        const values = data.map(d => parseInt(d.total));
        const bgColors = data.map(d => statusColors[d.estado] || colors.primary);

        charts.status = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                }
            }
        });
    }

    /**
     * Gráfica mensual (Barras)
     */
    function renderMonthlyChart(data) {
        const ctx = document.getElementById('chart-monthly');
        if (!ctx) return;

        if (charts.monthly) {
            charts.monthly.destroy();
        }

        const labels = data.map(d => d.mes_label);
        const reservas = data.map(d => parseInt(d.total));
        const pasajeros = data.map(d => parseInt(d.pasajeros));

        charts.monthly = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Reservas',
                        data: reservas,
                        backgroundColor: colors.primary,
                        borderRadius: 6,
                        borderSkipped: false
                    },
                    {
                        label: 'Pasajeros',
                        data: pasajeros,
                        backgroundColor: colors.cyan,
                        borderRadius: 6,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9'
                        }
                    }
                }
            }
        });
    }

    /**
     * Tabla de países
     */
    function renderCountriesTable(data, total) {
        const $tbody = $('#table-countries tbody');
        $tbody.empty();

        if (!data || data.length === 0) {
            $tbody.html('<tr><td colspan="5" style="text-align:center;color:#94a3b8;">No hay datos</td></tr>');
            return;
        }

        data.forEach((item, index) => {
            const percentage = total > 0 ? ((item.total / total) * 100).toFixed(1) : 0;
            const row = `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td>${escapeHtml(item.pais)}</td>
                    <td><strong>${item.total}</strong></td>
                    <td>${item.pasajeros || 0}</td>
                    <td>
                        <div class="rtt-progress-bar">
                            <div class="rtt-progress-fill" style="width: ${percentage}%"></div>
                        </div>
                        <small>${percentage}%</small>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    /**
     * Tabla de reservas recientes
     */
    function renderRecentTable(data) {
        const $tbody = $('#table-recent tbody');
        $tbody.empty();

        if (!data || data.length === 0) {
            $tbody.html('<tr><td colspan="4" style="text-align:center;color:#94a3b8;">No hay datos</td></tr>');
            return;
        }

        data.forEach(item => {
            const statusClass = 'rtt-status-' + item.estado;
            const statusLabel = item.estado.charAt(0).toUpperCase() + item.estado.slice(1);
            const row = `
                <tr>
                    <td><code>${escapeHtml(item.codigo)}</code></td>
                    <td title="${escapeHtml(item.tour)}">${truncateText(item.tour, 20)}</td>
                    <td>${escapeHtml(item.nombre_representante)}</td>
                    <td><span class="rtt-status-badge ${statusClass}">${statusLabel}</span></td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // === Utilidades ===

    function formatNumber(num) {
        return new Intl.NumberFormat('es-PE').format(num || 0);
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
    }

    function formatDateFull(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        const options = { weekday: 'short', day: 'numeric', month: 'short' };
        return date.toLocaleDateString('es-PE', options);
    }

    function truncateText(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function createGradientArray(ctx, color1, color2, length) {
        const result = [];
        for (let i = 0; i < length; i++) {
            const ratio = i / (length - 1 || 1);
            result.push(interpolateColor(color1, color2, ratio));
        }
        return result;
    }

    function interpolateColor(color1, color2, ratio) {
        const r1 = parseInt(color1.slice(1, 3), 16);
        const g1 = parseInt(color1.slice(3, 5), 16);
        const b1 = parseInt(color1.slice(5, 7), 16);

        const r2 = parseInt(color2.slice(1, 3), 16);
        const g2 = parseInt(color2.slice(3, 5), 16);
        const b2 = parseInt(color2.slice(5, 7), 16);

        const r = Math.round(r1 + (r2 - r1) * ratio);
        const g = Math.round(g1 + (g2 - g1) * ratio);
        const b = Math.round(b1 + (b2 - b1) * ratio);

        return `rgb(${r}, ${g}, ${b})`;
    }

})(jQuery);
