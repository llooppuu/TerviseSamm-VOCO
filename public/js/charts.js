if (typeof Chart === 'undefined') {
  console.warn('Chart.js not loaded');
}

function createLineChart(ctx, config) {
  return new Chart(ctx, {
    type: 'line',
    data: {
      labels: config.labels,
      datasets: [{
        label: config.label,
        data: config.values,
        borderColor: config.color,
        backgroundColor: config.color,
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 3,
        tension: 0.32,
        fill: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        intersect: false,
        mode: 'index',
      },
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        x: {
          grid: {
            display: false,
          },
          ticks: {
            color: '#657185',
            maxRotation: 0,
            autoSkipPadding: 18,
          },
        },
        y: {
          beginAtZero: true,
          grid: {
            color: 'rgba(18,22,31,0.08)',
          },
          ticks: {
            color: '#657185',
          },
        },
      },
    },
  });
}
