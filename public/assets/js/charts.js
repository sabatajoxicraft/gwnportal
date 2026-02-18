/**
 * Chart.js initialization for dashboard analytics
 * Supports admin and manager dashboards with role-based styling
 */

// Admin Dashboard Charts
function initAdminCharts(chartData) {
    const studentsChartEl = document.getElementById('studentsChart');
    
    if (!studentsChartEl || !chartData || chartData.length === 0) {
        return;
    }
    
    // Extract labels and data
    const labels = chartData.map(item => item.accommodation);
    const data = chartData.map(item => parseInt(item.student_count));
    
    // Admin gradient colors (purple theme)
    const ctx = studentsChartEl.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(155, 89, 182, 0.8)');
    gradient.addColorStop(1, 'rgba(205, 132, 241, 0.8)');
    
    new Chart(studentsChartEl, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Students',
                data: data,
                backgroundColor: gradient,
                borderColor: 'rgba(155, 89, 182, 1)',
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return 'Students: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 11
                        },
                        maxRotation: 45,
                        minRotation: 0
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Manager Dashboard Charts
function initManagerCharts(statusData) {
    const statusChartEl = document.getElementById('statusChart');
    
    if (!statusChartEl || !statusData) {
        return;
    }
    
    const data = [
        statusData.active || 0,
        statusData.pending || 0,
        statusData.inactive || 0
    ];
    
    // Manager gradient colors (blue theme)
    const colors = [
        'rgba(46, 213, 115, 0.8)',  // Green for active
        'rgba(255, 159, 64, 0.8)',   // Orange for pending
        'rgba(231, 76, 60, 0.8)'     // Red for inactive
    ];
    
    const borderColors = [
        'rgba(46, 213, 115, 1)',
        'rgba(255, 159, 64, 1)',
        'rgba(231, 76, 60, 1)'
    ];
    
    new Chart(statusChartEl, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Pending', 'Inactive'],
            datasets: [{
                label: 'Student Status',
                data: data,
                backgroundColor: colors,
                borderColor: borderColors,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Export functions for global access
if (typeof window !== 'undefined') {
    window.initAdminCharts = initAdminCharts;
    window.initManagerCharts = initManagerCharts;
}
