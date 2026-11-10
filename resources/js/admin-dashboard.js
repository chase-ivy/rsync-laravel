// Admin dashboard: the "Monthly Earnings Breakdown" pie chart (same settings as admin_dashboard.php).
import { Chart, PieController, ArcElement, Legend, Tooltip } from 'chart.js';

Chart.register(PieController, ArcElement, Legend, Tooltip);

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('monthlyEarningsChart');
    if (!canvas) return; // no verified payments this year

    const rows = JSON.parse(canvas.dataset.chart || '[]');

    new Chart(canvas.getContext('2d'), {
        type: 'pie',
        data: {
            labels: rows.map((r) => r.month),
            datasets: [{
                data: rows.map((r) => r.amount),
                backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#F472B6',
                    '#14B8A6', '#6366F1', '#F97316', '#84CC16', '#06B6D4', '#A855F7'],
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { enabled: true },
            },
        },
    });
});