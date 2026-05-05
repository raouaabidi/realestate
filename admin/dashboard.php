<?php
session_start();
require("config.php");

if(!isset($_SESSION['auser'])) {
    header("location:index.php");
    exit;
}

// ── Stat queries ──────────────────────────────────────────────
$q_users    = mysqli_query($con,"SELECT COUNT(*) as c FROM user WHERE utype='user'");
$q_agents   = mysqli_query($con,"SELECT COUNT(*) as c FROM user WHERE utype='agent'");
$q_builders = mysqli_query($con,"SELECT COUNT(*) as c FROM user WHERE utype='builder'");
$q_props    = mysqli_query($con,"SELECT COUNT(*) as c FROM property");
$q_apt      = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE type='apartment'");
$q_house    = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE type='house'");
$q_bldg     = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE type='building'");
$q_flat     = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE type='flat'");
$q_sale     = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE stype='sale'");
$q_rent     = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE stype='rent'");
$q_contact  = mysqli_query($con,"SELECT COUNT(*) as c FROM contact");
$q_feedback = mysqli_query($con,"SELECT COUNT(*) as c FROM feedback");

$users    = mysqli_fetch_assoc($q_users)['c'];
$agents   = mysqli_fetch_assoc($q_agents)['c'];
$builders = mysqli_fetch_assoc($q_builders)['c'];
$props    = mysqli_fetch_assoc($q_props)['c'];
$apt      = mysqli_fetch_assoc($q_apt)['c'];
$house    = mysqli_fetch_assoc($q_house)['c'];
$bldg     = mysqli_fetch_assoc($q_bldg)['c'];
$flat     = mysqli_fetch_assoc($q_flat)['c'];
$sale     = mysqli_fetch_assoc($q_sale)['c'];
$rent     = mysqli_fetch_assoc($q_rent)['c'];
$contacts = mysqli_fetch_assoc($q_contact)['c'];
$feedbacks= mysqli_fetch_assoc($q_feedback)['c'];

$total_users = $users + $agents + $builders;
$occupancy   = $props > 0 ? round(($sale / max($props,1)) * 100) : 0;

// Price data for line chart
$q_prices = mysqli_query($con,"SELECT city, AVG(price) as avg_price FROM property GROUP BY city ORDER BY avg_price DESC LIMIT 7");
$price_labels = []; $price_values = [];
if($q_prices) {
    while($row = mysqli_fetch_assoc($q_prices)){
        $price_labels[] = $row['city'] ?: 'N/A';
        $price_values[] = (int)$row['avg_price'];
    }
}
if(empty($price_labels)){ $price_labels = ['No data']; $price_values = [0]; }

// Properties per city for bar chart
$q_cities = mysqli_query($con,"SELECT city, COUNT(*) as cnt FROM property GROUP BY city ORDER BY cnt DESC LIMIT 7");
$city_labels = []; $city_sale = []; $city_rent = [];
if($q_cities){
    while($row = mysqli_fetch_assoc($q_cities)){
        $lbl = $row['city'] ?: 'N/A';
        $city_labels[] = $lbl;
        $rs = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE city='".mysqli_real_escape_string($con,$lbl)."' AND stype='sale'");
        $rr = mysqli_query($con,"SELECT COUNT(*) as c FROM property WHERE city='".mysqli_real_escape_string($con,$lbl)."' AND stype='rent'");
        $city_sale[] = (int)mysqli_fetch_assoc($rs)['c'];
        $city_rent[] = (int)mysqli_fetch_assoc($rr)['c'];
    }
}
if(empty($city_labels)){ $city_labels=['No data']; $city_sale=[0]; $city_rent=[0]; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Dashboard</title>

<!-- Existing admin assets -->
<link rel="shortcut icon" type="image/x-icon" href="assets/img/rsadmin.png">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/font-awesome.min.css">
<link rel="stylesheet" href="assets/css/feathericon.min.css">
<link rel="stylesheet" href="assets/css/style.css">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ── Design tokens ─────────────────────────────────── */
:root {
  --gold:     #F5B800;
  --gold-lt:  #FFF0B3;
  --gold-dk:  #C99300;
  --blue:     #1A5CFF;
  --blue-lt:  #EEF3FF;
  --blue-dk:  #0D3DB5;
  --navy:     #0B1D3A;
  --slate:    #4A5568;
  --mist:     #F7F9FC;
  --border:   #E8ECF2;
  --white:    #FFFFFF;
  --success:  #22C55E;
  --danger:   #EF4444;
  --radius:   14px;
  --shadow-sm:0 2px 8px rgba(0,0,0,.06);
  --shadow-md:0 4px 20px rgba(0,0,0,.10);
  --shadow-lg:0 8px 32px rgba(0,0,0,.14);
}

/* ── Global overrides ──────────────────────────────── */
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--mist); }

/* ── Dashboard wrapper ─────────────────────────────── */
.db-wrap { padding: 28px 32px; }

/* ── Section headers ───────────────────────────────── */
.db-section-title {
  font-size:11px; font-weight:700; letter-spacing:.08em;
  text-transform:uppercase; color:var(--slate);
  margin:32px 0 14px; display:flex; align-items:center; gap:8px;
}
.db-section-title::after {
  content:''; flex:1; height:1px; background:var(--border);
}

/* ── KPI cards ─────────────────────────────────────── */
.kpi-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
  gap:16px; margin-bottom:8px;
}
.kpi-card {
  background:var(--white);
  border-radius:var(--radius);
  padding:20px 20px 16px;
  box-shadow:var(--shadow-sm);
  border:1px solid var(--border);
  transition:transform .2s,box-shadow .2s;
  position:relative; overflow:hidden;
}
.kpi-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); }
.kpi-card::before {
  content:''; position:absolute; top:0; left:0; right:0;
  height:3px; background:var(--accent,var(--blue));
}
.kpi-icon {
  width:40px;height:40px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:18px; margin-bottom:12px;
  background:var(--icon-bg,var(--blue-lt));
  color:var(--icon-color,var(--blue));
}
.kpi-value {
  font-size:28px; font-weight:700; color:var(--navy);
  line-height:1; margin-bottom:4px;
}
.kpi-label { font-size:12px; color:var(--slate); font-weight:500; }
.kpi-bar {
  margin-top:12px; height:3px; background:var(--border);
  border-radius:99px; overflow:hidden;
}
.kpi-bar-fill {
  height:100%; border-radius:99px;
  background:var(--accent,var(--blue));
  width:var(--pct,50%);
}

/* Gold accent cards */
.kpi-card.gold { --accent:var(--gold); --icon-bg:#FFF8E1; --icon-color:var(--gold-dk); }
.kpi-card.blue  { --accent:var(--blue); --icon-bg:var(--blue-lt); --icon-color:var(--blue); }
.kpi-card.green { --accent:var(--success); --icon-bg:#F0FDF4; --icon-color:var(--success); }
.kpi-card.red   { --accent:var(--danger); --icon-bg:#FFF1F1; --icon-color:var(--danger); }

/* ── Chart grid ────────────────────────────────────── */
.chart-grid {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:20px; margin-bottom:20px;
}
.chart-grid-3 {
  display:grid;
  grid-template-columns:1fr 1fr 1fr;
  gap:20px; margin-bottom:20px;
}
@media(max-width:1100px){ .chart-grid-3{ grid-template-columns:1fr 1fr; } }
@media(max-width:768px) {
  .chart-grid, .chart-grid-3 { grid-template-columns:1fr; }
  .db-wrap { padding:16px; }
}

/* ── Chart cards ───────────────────────────────────── */
.chart-card {
  background:var(--white);
  border-radius:var(--radius);
  padding:22px;
  box-shadow:var(--shadow-sm);
  border:1px solid var(--border);
}
.chart-card.span2 { grid-column:span 2; }
@media(max-width:768px){ .chart-card.span2{ grid-column:span 1; } }

.chart-card-header {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:18px;
}
.chart-title {
  font-size:14px; font-weight:700; color:var(--navy);
}
.chart-subtitle {
  font-size:11px; color:var(--slate); margin-top:2px;
}
.chart-badge {
  font-size:10px; font-weight:600; padding:4px 10px;
  border-radius:99px; background:var(--blue-lt); color:var(--blue);
}
.chart-canvas-wrap { position:relative; }

/* ── Gauge ─────────────────────────────────────────── */
.gauge-wrap {
  display:flex; flex-direction:column; align-items:center;
  justify-content:center; gap:6px;
}
.gauge-value {
  font-size:36px; font-weight:800; color:var(--navy);
  margin-top:-8px;
}
.gauge-label { font-size:12px; color:var(--slate); font-weight:500; }
.gauge-range {
  display:flex; justify-content:space-between;
  width:100%; font-size:10px; color:var(--slate); margin-top:4px;
}

/* ── Legend pills ──────────────────────────────────── */
.legend-row {
  display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;
}
.legend-pill {
  display:flex; align-items:center; gap:5px;
  font-size:11px; color:var(--slate); font-weight:500;
}
.legend-dot {
  width:8px; height:8px; border-radius:50%;
}

/* ── Stat summary row ──────────────────────────────── */
.stat-row {
  display:flex; gap:16px; margin-top:14px; padding-top:14px;
  border-top:1px solid var(--border);
}
.stat-item { flex:1; text-align:center; }
.stat-item-val { font-size:18px; font-weight:700; color:var(--navy); }
.stat-item-lbl { font-size:10px; color:var(--slate); margin-top:2px; }
</style>
</head>
<body>

<?php include("header.php"); ?>

<div class="page-wrapper">
<div class="content container-fluid">
<div class="db-wrap">

  <!-- Page title -->
  <div style="margin-bottom:24px;">
    <h2 style="font-size:22px;font-weight:800;color:var(--navy);margin:0">Welcome back, Admin 👋</h2>
    <p style="font-size:13px;color:var(--slate);margin:4px 0 0">Real estate overview — <?= date('l, F j Y') ?></p>
  </div>

  <!-- ── KPI Row 1: People ── -->
  <div class="db-section-title">Users & Roles</div>
  <div class="kpi-grid">

    <div class="kpi-card blue" style="--pct:<?= $total_users > 0 ? round($users/$total_users*100) : 0 ?>%">
      <div class="kpi-icon"><i class="fe fe-users"></i></div>
      <div class="kpi-value"><?= $users ?></div>
      <div class="kpi-label">Registered Users</div>
      <div class="kpi-bar"><div class="kpi-bar-fill"></div></div>
    </div>

    <div class="kpi-card green" style="--pct:<?= $total_users > 0 ? round($agents/$total_users*100) : 0 ?>%">
      <div class="kpi-icon"><i class="fe fe-user-check"></i></div>
      <div class="kpi-value"><?= $agents ?></div>
      <div class="kpi-label">Agents</div>
      <div class="kpi-bar"><div class="kpi-bar-fill"></div></div>
    </div>

    <div class="kpi-card red" style="--pct:<?= $total_users > 0 ? round($builders/$total_users*100) : 0 ?>%">
      <div class="kpi-icon"><i class="fe fe-tool"></i></div>
      <div class="kpi-value"><?= $builders ?></div>
      <div class="kpi-label">Builders</div>
      <div class="kpi-bar"><div class="kpi-bar-fill"></div></div>
    </div>

    <div class="kpi-card gold" style="--pct:100%">
      <div class="kpi-icon"><i class="fe fe-home"></i></div>
      <div class="kpi-value"><?= $props ?></div>
      <div class="kpi-label">Total Properties</div>
      <div class="kpi-bar"><div class="kpi-bar-fill"></div></div>
    </div>

    <div class="kpi-card blue" style="--pct:<?= $contacts > 0 ? min(100,$contacts*10) : 0 ?>%">
      <div class="kpi-icon"><i class="fe fe-mail"></i></div>
      <div class="kpi-value"><?= $contacts ?></div>
      <div class="kpi-label">Contact Messages</div>
      <div class="kpi-bar"><div class="kpi-bar-fill"></div></div>
    </div>

    <div class="kpi-card green" style="--pct:<?= $feedbacks > 0 ? min(100,$feedbacks*10) : 0 ?>%">
      <div class="kpi-icon"><i class="fe fe-message-circle"></i></div>
      <div class="kpi-value"><?= $feedbacks ?></div>
      <div class="kpi-label">Feedbacks</div>
      <div class="kpi-bar"><div class="kpi-bar-fill"></div></div>
    </div>

  </div>

  <!-- ── Charts Row 1 ── -->
  <div class="db-section-title">Property Analytics</div>
  <div class="chart-grid">

    <!-- Bar chart: Sale vs Rent per city -->
    <div class="chart-card span2">
      <div class="chart-card-header">
        <div>
          <div class="chart-title">Properties by City</div>
          <div class="chart-subtitle">Sale vs Rental breakdown per location</div>
        </div>
        <span class="chart-badge">Bar Chart</span>
      </div>
      <div class="chart-canvas-wrap" style="height:260px">
        <canvas id="barChart"></canvas>
      </div>
      <div class="legend-row">
        <div class="legend-pill"><div class="legend-dot" style="background:#1A5CFF"></div>For Sale</div>
        <div class="legend-pill"><div class="legend-dot" style="background:#F5B800"></div>For Rent</div>
      </div>
    </div>

  </div>

  <div class="chart-grid-3">

    <!-- Pie: Property types -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <div class="chart-title">Property Types</div>
          <div class="chart-subtitle">Distribution by category</div>
        </div>
        <span class="chart-badge">Pie Chart</span>
      </div>
      <div class="chart-canvas-wrap" style="height:200px">
        <canvas id="pieChart"></canvas>
      </div>
      <div class="legend-row" style="margin-top:10px;">
        <div class="legend-pill"><div class="legend-dot" style="background:#1A5CFF"></div>Apartment (<?= $apt ?>)</div>
        <div class="legend-pill"><div class="legend-dot" style="background:#F5B800"></div>House (<?= $house ?>)</div>
        <div class="legend-pill"><div class="legend-dot" style="background:#22C55E"></div>Building (<?= $bldg ?>)</div>
        <div class="legend-pill"><div class="legend-dot" style="background:#EF4444"></div>Flat (<?= $flat ?>)</div>
      </div>
    </div>

    <!-- Gauge: Sale occupancy -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <div class="chart-title">Sale Rate</div>
          <div class="chart-subtitle">% of properties listed for sale</div>
        </div>
        <span class="chart-badge">Gauge</span>
      </div>
      <div class="gauge-wrap" style="height:200px;justify-content:flex-end;padding-bottom:10px">
        <canvas id="gaugeChart" style="max-height:160px"></canvas>
        <div class="gauge-value"><?= $occupancy ?>%</div>
        <div class="gauge-label">Sale Occupancy</div>
        <div class="gauge-range" style="max-width:220px">
          <span>0%</span><span>50%</span><span>100%</span>
        </div>
      </div>
      <div class="stat-row">
        <div class="stat-item">
          <div class="stat-item-val" style="color:#1A5CFF"><?= $sale ?></div>
          <div class="stat-item-lbl">For Sale</div>
        </div>
        <div class="stat-item">
          <div class="stat-item-val" style="color:#F5B800"><?= $rent ?></div>
          <div class="stat-item-lbl">For Rent</div>
        </div>
        <div class="stat-item">
          <div class="stat-item-val" style="color:#22C55E"><?= $props ?></div>
          <div class="stat-item-lbl">Total</div>
        </div>
      </div>
    </div>

    <!-- User role donut -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <div class="chart-title">User Roles</div>
          <div class="chart-subtitle">Breakdown of all registered accounts</div>
        </div>
        <span class="chart-badge">Donut</span>
      </div>
      <div class="chart-canvas-wrap" style="height:200px">
        <canvas id="donutChart"></canvas>
      </div>
      <div class="legend-row" style="margin-top:10px;">
        <div class="legend-pill"><div class="legend-dot" style="background:#1A5CFF"></div>Users (<?= $users ?>)</div>
        <div class="legend-pill"><div class="legend-dot" style="background:#22C55E"></div>Agents (<?= $agents ?>)</div>
        <div class="legend-pill"><div class="legend-dot" style="background:#EF4444"></div>Builders (<?= $builders ?>)</div>
      </div>
    </div>

  </div>

  <!-- ── Line chart: Avg price per city ── -->
  <div class="db-section-title">Price Intelligence</div>
  <div class="chart-card" style="margin-bottom:32px">
    <div class="chart-card-header">
      <div>
        <div class="chart-title">Average Price per City</div>
        <div class="chart-subtitle">Market price overview across locations (TND)</div>
      </div>
      <span class="chart-badge">Line Chart</span>
    </div>
    <div class="chart-canvas-wrap" style="height:260px">
      <canvas id="lineChart"></canvas>
    </div>
  </div>

</div><!-- /db-wrap -->
</div>
</div>

<!-- ── Scripts ───────────────────────────────────────── -->
<script src="assets/js/jquery-3.2.1.min.js"></script>
<script src="assets/js/popper.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/plugins/slimscroll/jquery.slimscroll.min.js"></script>
<script src="assets/js/script.js"></script>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ── Shared defaults ──────────────────────────────────
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#4A5568';

const BLUE   = '#1A5CFF';
const GOLD   = '#F5B800';
const GREEN  = '#22C55E';
const RED    = '#EF4444';
const NAVY   = '#0B1D3A';
const BORDER = '#E8ECF2';

// ── Bar chart ────────────────────────────────────────
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($city_labels) ?>,
    datasets: [
      {
        label: 'For Sale',
        data: <?= json_encode($city_sale) ?>,
        backgroundColor: BLUE,
        borderRadius: 6,
        borderSkipped: false,
      },
      {
        label: 'For Rent',
        data: <?= json_encode($city_rent) ?>,
        backgroundColor: GOLD,
        borderRadius: 6,
        borderSkipped: false,
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, border: { display: false } },
      y: {
        grid: { color: BORDER },
        border: { display: false, dash: [4,4] },
        ticks: { stepSize: 1 }
      }
    }
  }
});

// ── Pie chart ────────────────────────────────────────
new Chart(document.getElementById('pieChart'), {
  type: 'pie',
  data: {
    labels: ['Apartment','House','Building','Flat'],
    datasets: [{
      data: [<?= $apt ?>, <?= $house ?>, <?= $bldg ?>, <?= $flat ?>],
      backgroundColor: [BLUE, GOLD, GREEN, RED],
      borderWidth: 2, borderColor: '#fff',
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } }
  }
});

// ── Gauge (doughnut half) ────────────────────────────
const occupancy = <?= $occupancy ?>;
new Chart(document.getElementById('gaugeChart'), {
  type: 'doughnut',
  data: {
    datasets: [{
      data: [occupancy, 100 - occupancy],
      backgroundColor: [BLUE, BORDER],
      borderWidth: 0,
      circumference: 180,
      rotation: 270,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '72%',
    plugins: { legend: { display: false }, tooltip: { enabled: false } }
  }
});

// ── Donut: user roles ────────────────────────────────
new Chart(document.getElementById('donutChart'), {
  type: 'doughnut',
  data: {
    labels: ['Users','Agents','Builders'],
    datasets: [{
      data: [<?= $users ?>, <?= $agents ?>, <?= $builders ?>],
      backgroundColor: [BLUE, GREEN, RED],
      borderWidth: 3, borderColor: '#fff',
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '65%',
    plugins: { legend: { display: false } }
  }
});

// ── Line chart: price per city ───────────────────────
new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($price_labels) ?>,
    datasets: [{
      label: 'Avg Price (TND)',
      data: <?= json_encode($price_values) ?>,
      borderColor: BLUE,
      backgroundColor: 'rgba(26,92,255,0.08)',
      borderWidth: 2.5,
      pointBackgroundColor: BLUE,
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
      pointRadius: 5,
      pointHoverRadius: 7,
      fill: true,
      tension: 0.4
    },{
      label: 'Avg Price (TND)',
      data: <?= json_encode($price_values) ?>,
      borderColor: GOLD,
      backgroundColor: 'rgba(245,184,0,0.06)',
      borderWidth: 1.5,
      borderDash: [5,4],
      pointRadius: 0,
      fill: false,
      tension: 0.4
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => ' ' + ctx.parsed.y.toLocaleString() + ' TND'
        }
      }
    },
    scales: {
      x: { grid: { display: false }, border: { display: false } },
      y: {
        grid: { color: BORDER },
        border: { display: false, dash: [4,4] },
        ticks: { callback: v => v.toLocaleString() + ' TND' }
      }
    }
  }
});
</script>
</body>
</html>