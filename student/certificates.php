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
            background: white; padding: 25px 30px; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; color: #1a1a1a; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #e5e7eb; color: #4b5563; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .certificates-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 25px;
        }
        
        .certificate-card {
            background: white; border-radius: 15px; padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;
            overflow: hidden; border: 3px solid #e5e7eb;
        }
        .certificate-card:hover {
            transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .certificate-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 30px; text-align: center; color: white;
        }
        .certificate-icon {
            font-size: 48px; margin-bottom: 15px;
        }
        .certificate-header h3 {
            font-size: 20px; margin-bottom: 8px;
        }
        .certificate-code {
            font-size: 13px; opacity: 0.9; font-family: monospace;
        }
        
        .certificate-body {
            padding: 25px;
        }
        .certificate-info {
            margin-bottom: 20px; padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row {
            display: flex; justify-content: space-between;
            margin-bottom: 12px; font-size: 14px;
        }
        .info-label { color: #6b7280; }
        .info-value { font-weight: 600; color: #1a1a1a; }
        
        .grade-display {
            text-align: center; padding: 20px;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border-radius: 10px; margin-bottom: 20px;
        }
        .grade-value {
            font-size: 48px; font-weight: 700;
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .grade-label { font-size: 14px; color: #065f46; margin-top: 5px; }
        
        .btn-download {
            width: 100%; text-align: center;
        }
        
        .empty-state {
            text-align: center; padding: 80px 20px; background: white;
            border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .certificates-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>🏆 My Certificates</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($certificates->num_rows > 0): ?>
                <div class="certificates-grid">
                    <?php while ($cert = $certificates->fetch_assoc()): ?>
                        <div class="certificate-card">
                            <div class="certificate-header">
                                <div class="certificate-icon">🏆</div>
                                <h3>Certificate of Completion</h3>
                                <div class="certificate-code">
                                    CERT-<?php echo strtoupper($cert['course_code']); ?>-<?php echo $cert['id']; ?>
                                </div>
                            </div>
                            
                            <div class="certificate-body">
                                <div class="certificate-info">
                                    <div class="info-row">
                                        <span class="info-label">Course:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['course_name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Category:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['category']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Duration:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['duration']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Instructor:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($cert['instructor_name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Completed:</span>
                                        <span class="info-value"><?php echo date('d M Y', strtotime($cert['completion_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="grade-display">
                                    <div class="grade-value"><?php echo number_format($cert['final_grade'], 1); ?></div>
                                    <div class="grade-label">Final Grade</div>
                                </div>
                                
                                <button onclick="generateCertificate('<?php echo $cert['course_name']; ?>', '<?php echo $student_name; ?>', '<?php echo date('F d, Y', strtotime($cert['completion_date'])); ?>', '<?php echo number_format($cert['final_grade'], 1); ?>')" 
                                        class="btn btn-primary btn-download">
                                    📥 Download Certificate
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏆</div>
                    <h2>No Certificates Yet</h2>
                    <p>Complete your courses to earn certificates!</p>
                    <br>
                    <a href="my_courses.php" class="btn btn-primary">View My Courses</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function generateCertificate(courseName, studentName, date, grade) {
            // Simple certificate generation (you can enhance this)
            const certWindow = window.open('', '_blank', 'width=800,height=600');
            certWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Certificate of Completion</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body {
                            font-family: 'Georgia', serif;
                            background: linear-gradient(135deg, #667eea, #764ba2);
                            padding: 40px;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            min-height: 100vh;
                        }
                        .certificate {
                            background: white;
                            padding: 60px;
                            max-width: 800px;
                            border: 20px solid #f0f0f0;
                            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 40px;
                        }
                        .logo { font-size: 48px; margin-bottom: 20px; }
                        h1 {
                            font-size: 48px;
                            color: #667eea;
                            margin-bottom: 10px;
                        }
                        .subtitle {
                            font-size: 20px;
                            color: #666;
                        }
                        .body {
                            text-align: center;
                            margin: 40px 0;
                            line-height: 2;
                        }
                        .student-name {
                            font-size: 36px;
                            font-weight: bold;
                            color: #333;
                            border-bottom: 3px solid #667eea;
                            display: inline-block;
                            padding: 10px 30px;
                            margin: 20px 0;
                        }
                        .course-name {
                            font-size: 28px;
                            color: #667eea;
                            font-weight: bold;
                            margin: 20px 0;
                        }
                        .grade {
                            font-size: 24px;
                            color: #10b981;
                            font-weight: bold;
                        }
                        .footer {
                            display: flex;
                            justify-content: space-around;
                            margin-top: 60px;
                            padding-top: 30px;
                            border-top: 2px solid #eee;
                        }
                        .signature {
                            text-align: center;
                        }
                        .signature-line {
                            border-top: 2px solid #333;
                            width: 200px;
                            margin: 10px auto;
                        }
                        @media print {
                            body { background: white; padding: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="certificate">
                        <div class="header">
                            <div class="logo">🎓</div>
                            <h1>Certificate of Completion</h1>
                            <div class="subtitle">Skynusa Academy</div>
                        </div>
                        
                        <div class="body">
                            <p>This is to certify that</p>
                            <div class="student-name">${studentName}</div>
                            <p>has successfully completed the course</p>
                            <div class="course-name">${courseName}</div>
                            <p>with a final grade of</p>
                            <div class="grade">${grade}/100</div>
                            <p style="margin-top: 40px;">Date of Completion: ${date}</p>
                        </div>
                        
                        <div class="footer">
                            <div class="signature">
                                <div class="signature-line"></div>
                                <div>Director</div>
                                <div>Skynusa Academy</div>
                            </div>
                            <div class="signature">
                                <div class="signature-line"></div>
                                <div>Course Instructor</div>
                            </div>
                        </div>
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                        }
                    <\/script>
                </body>
                </html>
            `);
        }
    </script>
</body>
</html>