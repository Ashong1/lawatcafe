// Chart.js, bundled instead of loaded from cdn.jsdelivr.net: the app works on
// the shop's LAN alone, and the CDN copy left the dashboard's charts blank
// whenever the internet link was down — exactly when the owner opens it to
// investigate. A separate entry so the guest portal never downloads it.
//
// Module scripts run in document order, so this runs AFTER app.js has already
// started Alpine. Pages that build charts from Alpine init() wait for
// 'charts:ready' when window.Chart isn't there yet.
import Chart from 'chart.js/auto';

window.Chart = Chart;
window.dispatchEvent(new Event('charts:ready'));
