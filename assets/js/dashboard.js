/**
 * Dashboard charts initialization
 */
window.initDashboardCharts = function () {
  'use strict';
  var el = document.getElementById('dashboardChart');
  if (!el || typeof Highcharts === 'undefined') return;

  var labels = [];
  var values = [];
  try {
    labels = JSON.parse(el.dataset.labels || '[]');
    values = JSON.parse(el.dataset.values || '[]');
  } catch (e) { return; }

  if (!labels.length) {
    el.innerHTML = '<div class="empty-state"><p class="empty-state__desc">ยังไม่มีข้อมูลกราฟ</p></div>';
    return;
  }

  Highcharts.chart('dashboardChart', {
    chart: { type: 'areaspline', backgroundColor: 'transparent', height: 280 },
    title: { text: null },
    credits: { enabled: false },
    legend: { enabled: false },
    xAxis: {
      categories: labels,
      lineColor: '#E5E7EB',
      tickColor: '#E5E7EB',
      labels: { style: { color: '#6B7280', fontSize: '12px' } },
    },
    yAxis: {
      title: { text: null },
      gridLineColor: '#F3F4F6',
      labels: { style: { color: '#6B7280', fontSize: '12px' } },
      allowDecimals: false,
    },
    tooltip: {
      backgroundColor: '#fff',
      borderColor: '#E5E7EB',
      borderRadius: 12,
      style: { fontSize: '13px' },
    },
    plotOptions: {
      areaspline: {
        fillOpacity: 0.15,
        marker: { radius: 4, fillColor: '#10B981' },
        lineWidth: 2,
      },
    },
    series: [{
      name: 'รายการยืม',
      data: values,
      color: '#10B981',
      fillColor: {
        linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
        stops: [[0, 'rgba(16,185,129,0.25)'], [1, 'rgba(16,185,129,0)']],
      },
    }],
  });
};
