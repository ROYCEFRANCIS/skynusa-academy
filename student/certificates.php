<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Get completed courses with certificates
$certificates = $conn->query("
    SELECT e.*, c.course_name, c.course_code, c.duration, c.category,
           u.full_name as instructor_name,
           e.updated_at as completion_date
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.instructor_id = u.id
    WHERE e.student_id = '$student_id' 
    AND e.status = 'completed'
    AND e.final_grade IS NOT NULL
    ORDER BY e.updated_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f0f2f5; color: #1a1a1a; }
        
        .dashboard { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px; background: linear-gradient(180deg, #1e3a8a 0%, #312e81 100%);
            color: white; padding: 30px 0; position: fixed; height: 100vh; overflow-y: auto;
        }
        .sidebar-header { padding: 0 30px 30px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.7); }
        
        .user-profile { padding: 25px 30px; background: rgba(255,255,255,0.05); margin: 20px 0; }
        .user-profile .avatar {
            width: 50px; height: 50px; background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: 700; margin-bottom: 10px;
        }
        
        .sidebar-menu { list-style: none; padding: 20px 0; }
        .sidebar-menu li { margin: 5px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 14px 30px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; font-size: 15px;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 35px; }
        .sidebar-menu a.active { background: rgba(255,255,255,0.15); color: white; border-left: 4px solid #60a5fa; }
        .sidebar-menu a span { margin-right: 12px; font-size: 18px; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 35px; border-radius: 20px; margin-bottom: 35px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            color: white; position: relative; overflow: hidden;
        }
        .header::before {
            content: '🏆';
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 120px;
            opacity: 0.1;
        }
        .header-content { position: relative; z-index: 1; }
        .header h1 { 
            font-size: 36px; margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .header p { font-size: 16px; opacity: 0.95; }
        .header-stats {
            display: flex; gap: 30px; margin-top: 25px;
        }
        .stat-item {
            background: rgba(255,255,255,0.2);
            padding: 15px 25px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .stat-item .number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-item .label {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .btn {
            padding: 12px 24px; border: none; border-radius: 10px; cursor: pointer; 
            font-weight: 600; text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px; transition: all 0.3s; font-size: 14px;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea, #764ba2); 
            color: white; 
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); 
        }
        .btn-secondary { 
            background: rgba(255,255,255,0.2); 
            color: white; 
            backdrop-filter: blur(10px);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .certificates-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); 
            gap: 30px;
        }
        
        .certificate-card {
            background: white; 
            border-radius: 20px; 
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
        }
        .certificate-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f59e0b, #10b981);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .certificate-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }
        .certificate-card:hover::before {
            opacity: 1;
        }
        
        .certificate-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 35px 30px;
            text-align: center;
            color: white;
            position: relative;
        }
        .certificate-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 20px solid #764ba2;
        }
        .certificate-icon {
            font-size: 56px; 
            margin-bottom: 15px;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .certificate-header h3 {
            font-size: 22px; 
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .certificate-code {
            font-size: 12px; 
            opacity: 0.9; 
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 5px;
        }
        
        .certificate-body {
            padding: 35px 30px 30px;
        }
        .certificate-info {
            margin-bottom: 25px;
        }
        .info-row {
            display: flex; 
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            margin-bottom: 10px;
            background: #f9fafb;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .info-row:hover {
            background: #f3f4f6;
            transform: translateX(5px);
        }
        .info-label { 
            color: #6b7280; 
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-value { 
            font-weight: 600; 
            color: #1a1a1a;
            font-size: 14px;
        }
        
        .grade-display {
            text-align: center; 
            padding: 30px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 15px; 
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }
        .grade-display::before {
            content: '⭐';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 40px;
            opacity: 0.3;
        }
        .grade-value {
            font-size: 56px; 
            font-weight: 800;
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        .grade-label { 
            font-size: 14px; 
            color: #065f46; 
            margin-top: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-download {
            width: 100%; 
            text-align: center;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center; 
            padding: 100px 40px; 
            background: white;
            border-radius: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .empty-state-icon { 
            font-size: 80px; 
            margin-bottom: 25px;
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .empty-state h2 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #1a1a1a;
        }
        .empty-state p {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header, .user-profile { display: none; }
            .sidebar-menu a { padding: 14px 20px; justify-content: center; }
            .sidebar-menu a span { margin-right: 0; }
            .sidebar-menu a:hover { padding-left: 20px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .certificates-grid { grid-template-columns: 1fr; }
            .header-stats { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="header-content">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                        <div>
                            <h1><i class="fas fa-certificate"></i> My Certificates</h1>
                            <p>Your achievements and completed courses</p>
                        </div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="header-stats">
                        <div class="stat-item">
                            <div class="number"><?php echo $certificates->num_rows; ?></div>
                            <div class="label">Total Certificates</div>
                        </div>
                        <div class="stat-item">
                            <div class="number">
                                <?php 
                                $certs = $certificates;
                                $total = 0;
                                $count = 0;
                                while($c = $certs->fetch_assoc()) {
                                    $total += $c['final_grade'];
                                    $count++;
                                }
                                echo $count > 0 ? number_format($total / $count, 1) : '0';
                                $certificates->data_seek(0);
                                ?>
                            </div>
                            <div class="label">Average Grade</div>
                        </div>
                    </div>
                </div>
            </header>
            
            <?php if ($certificates->num_rows > 0): ?>
                <div class="certificates-grid">
                    <?php while ($cert = $certificates->fetch_assoc()): ?>
                        <?php 
                        // Generate unique certificate number
                        $cert_number = 'SKY-' . date('Y', strtotime($cert['completion_date'])) . '-' . 
                                      strtoupper($cert['course_code']) . '-' . 
                                      str_pad($cert['id'], 5, '0', STR_PAD_LEFT);
                        ?>
                        <div class="certificate-card">
                            <div class="certificate-header">
                                <div class="certificate-icon">🏆</div>
                                <h3>Certificate of Completion</h3>
                                <div class="certificate-code">
                                    <?php echo $cert_number; ?>
                                </div>
                            </div>
                            
                            <div class="certificate-body">
                                <div class="certificate-info">
                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-book"></i> Course
                                        </span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['course_name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-tag"></i> Category
                                        </span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['category']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-clock"></i> Duration
                                        </span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['duration']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-user-tie"></i> Instructor
                                        </span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['instructor_name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-calendar-check"></i> Completed
                                        </span>
                                        <span class="info-value"><?php echo date('d M Y', strtotime($cert['completion_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="grade-display">
                                    <div class="grade-value"><?php echo number_format($cert['final_grade'], 1); ?></div>
                                    <div class="grade-label">Final Grade</div>
                                </div>
                                
                                <button onclick="openCert('<?php echo htmlspecialchars($cert['course_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student_name, ENT_QUOTES); ?>', '<?php echo date('F d, Y', strtotime($cert['completion_date'])); ?>', '<?php echo number_format($cert['final_grade'], 1); ?>', '<?php echo $cert_number; ?>', '<?php echo htmlspecialchars($cert['instructor_name'], ENT_QUOTES); ?>')" class="btn btn-primary btn-download">
                                    <i class="fas fa-download"></i> Download Certificate
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏆</div>
                    <h2>No Certificates Yet</h2>
                    <p>Complete your courses to earn certificates and showcase your achievements!</p>
                    <a href="my_courses.php" class="btn btn-primary">
                        <i class="fas fa-book"></i> View My Courses
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    function openCert(course, student, date, grade, certNum, instructor) {
        var w = window.open('', '_blank', 'width=900,height=1200');
        w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Certificate</title>' +
        '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">' +
        '<style>*{margin:0;padding:0;box-sizing:border-box}@page{size:A4 portrait;margin:0}' +
        'body{font-family:Inter,sans-serif;background:#f8fafc;padding:20px}' +
        '.cert{width:210mm;height:297mm;background:#fff;margin:0 auto;position:relative;box-shadow:0 0 30px rgba(0,0,0,0.1)}' +
        '.border{position:absolute;top:12mm;left:12mm;right:12mm;bottom:12mm;border:2px solid #e2e8f0}' +
        '.border-inner{position:absolute;top:15mm;left:15mm;right:15mm;bottom:15mm;border:1px solid #cbd5e1}' +
        '.header{text-align:center;padding:25mm 20mm 20mm}' +
        '.logo{max-width:160px;max-height:160px;margin:0 auto 12mm;display:block}' +
        '.company{font-family:"Playfair Display",serif;font-size:28px;font-weight:800;color:#1e293b;letter-spacing:4px;margin-bottom:6px}' +
        '.tagline{font-size:11px;color:#64748b;letter-spacing:2px;text-transform:uppercase;font-weight:600}' +
        '.content{padding:0 25mm}' +
        '.divider{width:80px;height:2px;background:linear-gradient(90deg,transparent,#3b82f6,transparent);margin:15mm auto}' +
        '.title{text-align:center;margin-bottom:15mm}' +
        '.title h1{font-family:"Playfair Display",serif;font-size:48px;font-weight:800;color:#1e293b;letter-spacing:6px;margin-bottom:6px}' +
        '.title p{font-size:14px;color:#64748b;letter-spacing:3px;text-transform:uppercase;font-weight:600}' +
        '.text{text-align:center;font-size:13px;color:#475569;margin:10mm 0;line-height:1.6}' +
        '.name-wrap{text-align:center;margin:15mm 0;position:relative}' +
        '.name{font-family:"Playfair Display",serif;font-size:38px;font-weight:700;color:#1e293b;display:inline-block;padding:0 15px 8px;border-bottom:3px solid #3b82f6}' +
        '.course-wrap{text-align:center;margin:12mm 0}' +
        '.course{font-family:"Playfair Display",serif;font-size:24px;font-weight:700;color:#3b82f6;line-height:1.4}' +
        '.grade-wrap{text-align:center;margin:15mm 0}' +
        '.grade-box{display:inline-block;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;padding:12px 45px;border-radius:40px;box-shadow:0 8px 24px rgba(59,130,246,0.25)}' +
        '.grade-lbl{font-size:10px;letter-spacing:2px;text-transform:uppercase;margin-bottom:5px;font-weight:600;opacity:0.95}' +
        '.grade-val{font-family:"Playfair Display",serif;font-size:36px;font-weight:800}' +
        '.info{text-align:center;margin-top:12mm;font-size:9px;color:#94a3b8;font-family:"Courier New",monospace;line-height:1.7}' +
        '.footer{position:absolute;bottom:22mm;left:25mm;right:25mm;display:flex;justify-content:space-between}' +
        '.sig{text-align:center;width:45%}' +
        '.sig-line{width:100%;height:1px;background:#cbd5e1;margin-bottom:8px}' +
        '.sig-name{font-weight:700;font-size:13px;color:#1e293b;margin-bottom:3px}' +
        '.sig-title{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}' +
        '.sig-date{font-size:9px;color:#94a3b8;margin-top:5px}' +
        '.ctrl{position:fixed;top:20px;right:20px;z-index:1000;display:flex;gap:10px}' +
        '.btn{padding:10px 22px;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:12px;font-family:Inter,sans-serif;transition:all 0.3s}' +
        '.btn-p{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;box-shadow:0 4px 12px rgba(59,130,246,0.3)}' +
        '.btn-p:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(59,130,246,0.4)}' +
        '.btn-c{background:#e5e7eb;color:#374151}' +
        '.btn-c:hover{background:#d1d5db}' +
        '@media print{body{background:#fff;padding:0}.cert{box-shadow:none}.ctrl{display:none!important}}' +
        '</style></head><body>' +
        '<div class="cert">' +
        '<div class="border"></div>' +
        '<div class="border-inner"></div>' +
        '<div class="header">' +
        '<img src="../assets/img/logo.png" class="logo" onerror="this.style.display=\'none\'">' +
        '<div class="company">SKYNUSA ACADEMY</div>' +
        '<div class="tagline">Excellence in Education</div>' +
        '</div>' +
        '<div class="content">' +
        '<div class="title">' +
        '<h1>CERTIFICATE</h1>' +
        '<p>Of Completion</p>' +
        '</div>' +
        '<div class="divider"></div>' +
        '<p class="text">This is to certify that</p>' +
        '<div class="name-wrap"><div class="name">' + student + '</div></div>' +
        '<p class="text">has successfully completed the course</p>' +
        '<div class="course-wrap"><div class="course">' + course + '</div></div>' +
        '<p class="text" style="font-size:12px;color:#64748b">with outstanding dedication and achievement</p>' +
        '<div class="grade-wrap">' +
        '<div class="grade-box">' +
        '<div class="grade-lbl">Final Grade</div>' +
        '<div class="grade-val">' + grade + '</div>' +
        '</div></div>' +
        '<div class="info">Certificate No: ' + certNum + '<br>Issue Date: ' + date + '</div>' +
        '</div>' +
        '<div class="footer">' +
        '<div class="sig">' +
        '<div class="sig-line"></div>' +
        '<div class="sig-name">Dr. Sarah Johnson</div>' +
        '<div class="sig-title">Academy Director</div>' +
        '<div class="sig-date">' + date + '</div>' +
        '</div>' +
        '<div class="sig">' +
        '<div class="sig-line"></div>' +
        '<div class="sig-name">' + instructor + '</div>' +
        '<div class="sig-title">Course Instructor</div>' +
        '<div class="sig-date">' + date + '</div>' +
        '</div></div></div>' +
        '<div class="ctrl">' +
        '<button class="btn btn-p" onclick="window.print()">Print</button>' +
        '<button class="btn btn-c" onclick="window.close()">Close</button>' +
        '</div></body></html>');
        w.document.close();
    }
    </script>
</body>
</html>