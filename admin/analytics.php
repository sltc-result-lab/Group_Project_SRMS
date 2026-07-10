<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
$subjects_stmt = $db->query("SELECT id, name, code, semester FROM subjects ORDER BY name ASC");
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Analytics - Result Management System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        /* Modern aesthetic requested by user:
           Soft shadows, rounded corners, clean spacing, blue-based color theme */
        :root{
            --primary:#2563eb;
            --primary-dark:#1d4ed8;
            --secondary:#0f172a;
            --bg:#f4f7fe; /* softer modern background */
            --card:#ffffff;
            --text:#1e293b;
            --muted:#64748b;
            --border:#e2e8f0;
            --success:#10b981;
            --warning:#f59e0b;
            --danger:#ef4444;
            --info:#0ea5e9;
            --purple:#8b5cf6;
        }

        body{
            margin:0;
            font-family: "Segoe UI", Arial, sans-serif;
            background:var(--bg);
            color:var(--text);
        }

        .app-wrapper{ display:flex; min-height:100vh; }

        .sidebar{
            width:270px;
            background:linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color:#fff;
            position:fixed; left:0; top:0; bottom:0; padding:24px 16px;
            overflow-y:auto; box-shadow:4px 0 24px rgba(0,0,0,0.06);
            z-index: 100;
        }
        .brand{ display:flex; align-items:center; gap:12px; padding:10px 12px 24px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:20px; }
        .brand-icon{ width:50px; height:50px; border-radius:14px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); display:flex; align-items:center; justify-content:center; font-size:22px; color:#fff; flex-shrink:0; }
        .brand h4{ margin:0; font-size:18px; font-weight:700; }
        .brand small{ color:#cbd5e1; }
        .sidebar-menu{ list-style:none; padding:0; margin:0; }
        .sidebar-menu li{ margin-bottom:8px; }
        .sidebar-menu a{
            text-decoration:none; color:#cbd5e1; display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:12px; transition:all 0.25s ease; font-size:15px; font-weight:500;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active{
            background:rgba(59,130,246,0.15); color:#fff; transform:translateX(4px);
        }

        .main-content{ margin-left:270px; width:calc(100% - 270px); min-height:100vh; display:flex; flex-direction:column; }
        .topbar{ background:#fff; padding:20px 32px; display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 16px; position:sticky; top:0; z-index:10; box-shadow:0 4px 20px rgba(0,0,0,0.03); }
        .topbar-left h2{ margin:0; font-size:24px; font-weight:700; color:var(--text); letter-spacing:-0.4px; }
        .topbar-left p{ margin:4px 0 0; color:var(--muted); font-size:14px; }
        
        .topbar-right{ display:flex; align-items:center; gap:20px; flex-wrap: wrap; }
        .filter-group{ display:flex; align-items:center; gap:12px; background:#f8fafc; padding:6px 16px; border-radius:12px; border:1px solid var(--border); }
        .filter-group select{ border:none; background:transparent; font-weight:600; color:var(--primary); outline:none; cursor:pointer; max-width: 250px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; }
        .admin-pill{ background:#eff6ff; color:var(--primary-dark); padding:8px 16px; border-radius:999px; font-weight:600; font-size:14px;}
        .logout-btn{ background:var(--bg); border:1px solid var(--border); color:var(--text); text-decoration:none; padding:8px 16px; border-radius:12px; font-weight:600; transition:0.2s; font-size:14px; }
        .logout-btn:hover{ background:#f1f5f9; color:var(--danger); border-color:#e2e8f0; }

        .content{ padding:32px; flex: 1; }

        .stat-card{ background:var(--card); border:none; border-radius:20px; padding:24px; box-shadow:0 8px 24px rgba(15,23,42,0.04); height:100%; display:flex; align-items:center; gap:20px; transition:transform 0.25s ease; border: 1px solid rgba(226, 232, 240, 0.6); }
        .stat-card:hover{ transform:translateY(-4px); box-shadow:0 12px 32px rgba(15,23,42,0.08); }
        .stat-icon{ width:64px; height:64px; border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; flex-shrink:0; }
        .icon-blue{ background:rgba(37,99,235,0.1); color:var(--primary); }
        .icon-green{ background:rgba(16,185,129,0.1); color:#059669; }
        .icon-red{ background:rgba(239,68,68,0.1); color:var(--danger); }
        .icon-purple{ background:rgba(139,92,246,0.1); color:var(--purple); }

        .stat-info { overflow: hidden; width: 100%; }
        .stat-info .stat-label{ color:var(--muted); font-size:14px; font-weight:600; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-info .stat-value{ font-size:28px; font-weight:800; color:var(--text); line-height:1.2; display:flex; align-items:baseline; flex-wrap:wrap; gap:6px; word-break: break-word; }
        .stat-value small{ font-size:14px; color:var(--muted); font-weight:500; text-transform:none; margin-top:2px;}

        .panel-card{ background:#fff; border:none; border-radius:24px; box-shadow:0 8px 24px rgba(15,23,42,0.04); overflow:hidden; margin-bottom:24px; border: 1px solid rgba(226, 232, 240, 0.6); }
        .panel-header{ padding:24px 28px; border-bottom:1px solid rgba(226,232,240,0.6); display:flex; justify-content:space-between; align-items:center; }
        .panel-header h5{ margin:0; font-weight:700; font-size:18px; color:var(--text); display:flex; align-items:center; gap:10px; }
        .panel-body{ padding:28px; }
        .chart-container{ position:relative; width: 100%; }
        
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 1000; display: none; justify-content: center; align-items: center; }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); width: 40px; height: 40px; border-radius: 50%; border-left-color: var(--primary); animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        @media (max-width: 992px){
            .sidebar{ position:relative; width:100%; height:auto; }
            .main-content{ margin-left:0; width:100%; }
            .app-wrapper{ display:block; }
            .topbar{ flex-direction:column; align-items:flex-start; gap:16px; padding:16px; }
            .topbar-right{ width:100%; justify-content:space-between; }
        }
    </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div></div>

<div class="app-wrapper">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div><h4>Result Portal</h4><small>Admin Panel</small></div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="analytics.php" class="active"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
            <li><a href="../students/list.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
            <li><a href="../subjects/list.php"><i class="fa-solid fa-book-open"></i> Subjects</a></li>
            <li><a href="../results/create.php"><i class="fa-solid fa-file-circle-plus"></i> Add Results</a></li>
            <li><a href="../results/list.php"><i class="fa-solid fa-table-list"></i> View Results</a></li>
            <li><a href="../results/publish.php"><i class="fa-solid fa-bullhorn"></i> Publish Results</a></li>
            <li><a href="change-password.php"><i class="fa-solid fa-key"></i> Change Password</a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> Backup Database</a></li>
            <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h2>Admin Analytics</h2>
                <p>Interactive dashboard updating dynamically via system data</p>
            </div>
            <div class="topbar-right">
                <div class="filter-group">
                    <i class="fa-solid fa-filter text-muted"></i>
                    <span class="text-muted small fw-bold">FILTER:</span>
                    <select id="semesterFilter" onchange="handleSemesterChange()">
                        <option value="">All Semesters</option>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                        <option value="3">Semester 3</option>
                        <option value="4">Semester 4</option>
                        <option value="5">Semester 5</option>
                        <option value="6">Semester 6</option>
                    </select>
                </div>
                <div class="filter-group">
                    <i class="fa-solid fa-book text-muted"></i>
                    <span class="text-muted small fw-bold">SUBJECT:</span>
                    <select id="subjectFilter" onchange="fetchDashboardData()">
                        <option value="">All Subjects</option>
                        <?php foreach($subjects as $subj): ?>
                            <option value="<?php echo $subj['id']; ?>"><?php echo htmlspecialchars($subj['name'] . ' (' . $subj['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button onclick="downloadPDFReport()" class="btn btn-primary d-flex align-items-center gap-2" style="border-radius:12px; padding:8px 16px; font-weight:600; font-size:14px; border:none; background-color: var(--primary);">
                    <i class="fa-solid fa-file-pdf"></i> Download Report
                </button>
                <div class="admin-pill"><i class="fa-solid fa-user-shield me-2"></i><?php echo htmlspecialchars($admin_username); ?></div>
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a>
            </div>
        </div>

        <div class="content">
            
            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Total Students</div>
                            <div class="stat-value" id="kpi-total-students">--</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon icon-green"><i class="fa-solid fa-arrow-trend-up"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Pass Rate</div>
                            <div class="stat-value"><span id="kpi-pass-rate">--</span> <small>%</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon icon-red"><i class="fa-solid fa-arrow-trend-down"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Fail Rate</div>
                            <div class="stat-value"><span id="kpi-fail-rate">--</span> <small>%</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon icon-purple"><i class="fa-solid fa-star"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Top Subject</div>
                            <div class="stat-value" id="kpi-top-subject" style="font-size: 20px;">--</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="panel-card">
                        <div class="panel-header">
                            <h5><i class="fa-solid fa-chart-column text-primary me-2"></i> Subject-wise Performance</h5>
                        </div>
                        <div class="panel-body">
                            <div class="chart-container" style="height: 380px;">
                                <canvas id="subjectChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="panel-card h-100">
                        <div class="panel-header">
                            <h5><i class="fa-solid fa-bars-staggered text-success me-2"></i> Grade Distribution</h5>
                        </div>
                        <div class="panel-body">
                            <div class="chart-container" style="height: 320px;">
                                <canvas id="gradeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="panel-card h-100">
                        <div class="panel-header">
                            <h5><i class="fa-solid fa-chart-column text-primary me-2"></i> Grade Normalization</h5>
                        </div>
                        <div class="panel-body">
                            <div class="chart-container" style="height: 320px;">
                                <canvas id="normGradeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    // Embed the subjects data fetched from database
    const allSubjects = <?php echo json_encode($subjects); ?>;

    // Global chart instances tracking
    let charts = {};

    function initCharts() {
        Chart.defaults.font.family = '"Segoe UI", Arial, sans-serif';
        Chart.defaults.color = '#64748b';
        Chart.defaults.scale.grid.color = 'rgba(226, 232, 240, 0.6)';

        // 1. Subject-wise Chart (Multi-bar)
        const ctxSubject = document.getElementById('subjectChart').getContext('2d');
        charts.subject = new Chart(ctxSubject, {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, padding: 20 } },
                    tooltip: { mode: 'index', intersect: false, backgroundColor: '#0f172a', titleFont: {size: 14}, padding: 12, cornerRadius: 8 }
                },
                scales: {
                    y: { beginAtZero: true, max: 100, title: { display: true, text: 'Percentage / Marks' } }
                },
                interaction: { mode: 'index', intersect: false },
                barPercentage: 0.7,
                categoryPercentage: 0.8
            }
        });

        // 2. Grade Distribution (Horizontal Bar)
        const ctxGrade = document.getElementById('gradeChart').getContext('2d');
        charts.grade = new Chart(ctxGrade, {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#0f172a', padding: 12, cornerRadius: 8 }
                },
                scales: {
                    x: { beginAtZero: true, grid: { borderDash: [4, 4] } }
                }
            }
        });

        // 3. Grade Normalization (Histogram)
        const ctxNorm = document.getElementById('normGradeChart').getContext('2d');
        charts.normGrade = new Chart(ctxNorm, {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#0f172a', padding: 12, cornerRadius: 8 }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Number of Students' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Part 1: Semester-based Subject Dropdown updates
    function updateSubjectDropdown() {
        const semesterFilter = document.getElementById('semesterFilter');
        const selectedSemester = semesterFilter.value;
        const subjectFilter = document.getElementById('subjectFilter');
        
        // Clear except first option
        subjectFilter.innerHTML = '<option value="">All Subjects</option>';
        
        allSubjects.forEach(subj => {
            if (selectedSemester === "" || String(subj.semester) === String(selectedSemester)) {
                const opt = document.createElement('option');
                opt.value = subj.id;
                opt.textContent = subj.name + ' (' + subj.code + ')';
                subjectFilter.appendChild(opt);
            }
        });
    }

    function handleSemesterChange() {
        updateSubjectDropdown();
        // Reset subject option back to default (All Subjects)
        document.getElementById('subjectFilter').value = "";
        fetchDashboardData();
    }

    function fetchDashboardData() {
        const semester = document.getElementById('semesterFilter').value;
        const subject = document.getElementById('subjectFilter').value;
        const loader = document.getElementById('loadingOverlay');
        loader.style.display = 'flex';

        fetch('get_dashboard_data.php?semester=' + encodeURIComponent(semester) + '&subject_id=' + encodeURIComponent(subject))
            .then(res => res.json())
            .then(data => {
                if(data.status !== 'success') {
                    console.error("Failed to load data: ", data.message);
                    return;
                }

                // Update KPIs
                document.getElementById('kpi-total-students').textContent = data.total_students;
                document.getElementById('kpi-pass-rate').textContent = data.pass_rate;
                document.getElementById('kpi-fail-rate').textContent = data.fail_rate;
                
                let topSubText = data.top_subject;
                if(data.top_subject_avg > 0) topSubText += ` <span style="font-size:14px;color:#64748b;font-weight:600">(${data.top_subject_avg} Avg)</span>`;
                document.getElementById('kpi-top-subject').innerHTML = topSubText;

                // Update Subject Chart
                charts.subject.data.labels = data.subject_wise.labels;
                charts.subject.data.datasets = [
                    {
                        label: 'Pass %',
                        data: data.subject_wise.pass_percentages,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    },
                    {
                        label: 'Fail %',
                        data: data.subject_wise.fail_percentages,
                        backgroundColor: '#ef4444',
                        borderRadius: 4
                    },
                    {
                        label: 'Average Marks',
                        data: data.subject_wise.averages,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4
                    }
                ];
                charts.subject.update();

                // Update Grade Distribution
                charts.grade.data.labels = data.grade_distribution.labels;
                charts.grade.data.datasets = [{
                    label: 'Students Count',
                    data: data.grade_distribution.data,
                    backgroundColor: [
                        '#8b5cf6', // Distinction - Purple
                        '#3b82f6', // First Class - Blue
                        '#0ea5e9', // Second Class - Sky
                        '#10b981', // Pass - Green
                        '#ef4444'  // Fail - Red
                    ],
                    borderRadius: 6
                }];
                charts.grade.update();

                // Update Grade Normalization
                charts.normGrade.data.labels = data.grade_normalization.labels;
                charts.normGrade.data.datasets = [
                    {
                        type: 'line',
                        label: 'Trend',
                        data: data.grade_normalization.data,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#f59e0b',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: false,
                        tension: 0.4
                    },
                    {
                        type: 'bar',
                        label: 'Students Count',
                        data: data.grade_normalization.data,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4
                    }
                ];
                charts.normGrade.update();
            })
            .catch(err => console.error("Error fetching data:", err))
            .finally(() => {
                setTimeout(() => loader.style.display = 'none', 300);
            });
    }

    // Part 2: PDF Report Download
    function downloadPDFReport() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        
        // Fetch current filter text
        const semesterSelect = document.getElementById('semesterFilter');
        const semesterText = semesterSelect.options[semesterSelect.selectedIndex].text;
        
        const subjectSelect = document.getElementById('subjectFilter');
        const subjectText = subjectSelect.options[subjectSelect.selectedIndex].text;
        
        // Fetch KPIs text
        const totalStudents = document.getElementById('kpi-total-students').textContent;
        const passRate = document.getElementById('kpi-pass-rate').textContent;
        const failRate = document.getElementById('kpi-fail-rate').textContent;
        const topSubject = document.getElementById('kpi-top-subject').innerText.replace(/\s+/g, ' '); // normalize whitespace
        
        // Date and Time
        const generatedTime = new Date().toLocaleString('en-LK', { timeZone: 'Asia/Colombo' });
        
        // Styling Helper
        const primaryColor = [37, 99, 235]; // #2563eb
        const darkColor = [15, 23, 42]; // #0f172a
        const mutedColor = [100, 116, 139]; // #64748b
        const lightBg = [248, 250, 252]; // #f8fafc
        const borderLight = [226, 232, 240]; // #e2e8f0
        
        // --- PAGE 1: Header & KPIs & Subject Chart ---
        
        // Document Header
        doc.setFillColor(...darkColor);
        doc.rect(0, 0, 210, 35, 'F');
        
        // Title
        doc.setTextColor(255, 255, 255);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(22);
        doc.text('Student Result Analytics Report', 15, 18);
        
        // Subtitle
        doc.setFont('Helvetica', 'normal');
        doc.setFontSize(10);
        doc.setTextColor(203, 213, 225);
        doc.text('System generated dashboard overview and statistics', 15, 26);
        
        // Generated time (right aligned in header)
        doc.text('Generated: ' + generatedTime, 195, 26, { align: 'right' });
        
        // Filter Details section
        doc.setFillColor(...lightBg);
        doc.rect(15, 45, 180, 20, 'F');
        doc.setDrawColor(...borderLight);
        doc.rect(15, 45, 180, 20);
        
        doc.setTextColor(...darkColor);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(10);
        doc.text('REPORT FILTERS', 20, 52);
        
        doc.setFont('Helvetica', 'normal');
        doc.setTextColor(...mutedColor);
        doc.text('Selected Semester: ', 20, 59);
        doc.setTextColor(...darkColor);
        doc.text(semesterText, 55, 59);
        
        doc.setTextColor(...mutedColor);
        doc.text('Selected Subject: ', 110, 59);
        doc.setTextColor(...darkColor);
        doc.text(subjectText, 142, 59);
        
        // KPI Cards Grid (4 boxes)
        const kpiWidth = 42;
        const kpiHeight = 25;
        const kpiY = 75;
        const kpiGap = 4;
        
        const kpis = [
            { label: 'TOTAL STUDENTS', val: totalStudents, desc: 'Students' },
            { label: 'PASS RATE', val: passRate + ' %', desc: 'Success percentage' },
            { label: 'FAIL RATE', val: failRate + ' %', desc: 'Failure percentage' },
            { label: 'TOP SUBJECT', val: topSubject.split(' (')[0], desc: topSubject.includes('(') ? '(' + topSubject.split(' (')[1] : 'Highest Average' }
        ];
        
        kpis.forEach((kpi, idx) => {
            const x = 15 + idx * (kpiWidth + kpiGap);
            doc.setFillColor(...lightBg);
            doc.rect(x, kpiY, kpiWidth, kpiHeight, 'F');
            doc.setDrawColor(...borderLight);
            doc.rect(x, kpiY, kpiWidth, kpiHeight);
            
            // Draw label
            doc.setFont('Helvetica', 'bold');
            doc.setFontSize(8);
            doc.setTextColor(...mutedColor);
            doc.text(kpi.label, x + 4, kpiY + 6);
            
            // Draw value
            doc.setFont('Helvetica', 'bold');
            doc.setFontSize(kpi.val.length > 15 ? 10 : 12);
            doc.setTextColor(...primaryColor);
            doc.text(kpi.val, x + 4, kpiY + 14);
            
            // Draw description
            doc.setFont('Helvetica', 'normal');
            doc.setFontSize(7);
            doc.setTextColor(...mutedColor);
            doc.text(kpi.desc, x + 4, kpiY + 21, { maxWidth: kpiWidth - 8 });
        });
        
        // Section Header 1
        doc.setTextColor(...darkColor);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(14);
        doc.text('Subject-wise Performance Analysis', 15, 115);
        doc.setDrawColor(...primaryColor);
        doc.setLineWidth(0.8);
        doc.line(15, 117, 35, 117);
        
        // Subject Chart Image
        const subjectChartImg = charts.subject.toBase64Image();
        doc.addImage(subjectChartImg, 'PNG', 15, 122, 180, 85);
        
        // Footer Page 1
        doc.setFont('Helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(...mutedColor);
        doc.text('Student Result Management System - Page 1 of 2', 105, 285, { align: 'center' });
        
        // --- PAGE 2: Grade Distribution & Grade Normalization ---
        doc.addPage();
        
        // Header Page 2 (Simple text title block)
        doc.setFillColor(...darkColor);
        doc.rect(0, 0, 210, 15, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(12);
        doc.text('Student Result Analytics Report - Detail Distribution', 15, 10);
        
        // Grade Distribution Chart Section
        doc.setTextColor(...darkColor);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(14);
        doc.text('Grade Distribution', 15, 30);
        doc.setDrawColor(...primaryColor);
        doc.setLineWidth(0.8);
        doc.line(15, 32, 35, 32);
        
        const gradeChartImg = charts.grade.toBase64Image();
        doc.addImage(gradeChartImg, 'PNG', 15, 37, 180, 80);
        
        // Grade Normalization Chart Section
        doc.setTextColor(...darkColor);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(14);
        doc.text('Grade Normalization', 15, 135);
        doc.setDrawColor(...primaryColor);
        doc.setLineWidth(0.8);
        doc.line(15, 137, 35, 137);
        
        const normGradeChartImg = charts.normGrade.toBase64Image();
        doc.addImage(normGradeChartImg, 'PNG', 15, 142, 180, 80);
        
        // Summary / Notes Section
        doc.setFillColor(...lightBg);
        doc.rect(15, 235, 180, 30, 'F');
        doc.setDrawColor(...borderLight);
        doc.rect(15, 235, 180, 30);
        
        doc.setTextColor(...darkColor);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(10);
        doc.text('Notes / Summary:', 20, 242);
        doc.setFont('Helvetica', 'normal');
        doc.setTextColor(...mutedColor);
        doc.setFontSize(8.5);
        doc.text('This report outlines the academic status and grade outcomes for ' + semesterText + ' under ' + subjectText + '. The figures represent verified evaluation results extracted directly from the primary databases.', 20, 248, { maxWidth: 170 });
        
        // Footer Page 2
        doc.setFont('Helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(...mutedColor);
        doc.text('Student Result Management System - Page 2 of 2', 105, 285, { align: 'center' });
        
        // Save File
        const sanitizeString = (str) => str.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        const semesterSlug = sanitizeString(semesterText);
        const subjectSlug = sanitizeString(subjectText);
        const pdfName = `analytics_report_${semesterSlug}_${subjectSlug}.pdf`;
        doc.save(pdfName);
    }

    // Initialize map and fetch default data
    document.addEventListener('DOMContentLoaded', () => {
        initCharts();
        updateSubjectDropdown();
        fetchDashboardData();
    });
</script>

</body>
</html>