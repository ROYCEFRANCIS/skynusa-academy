<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../index.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['full_name'];

// Get all students from instructor's courses
$students_query = "
    SELECT DISTINCT u.*, 
           COUNT(DISTINCT e.course_id) as enrolled_courses,
           AVG(e.progress) as avg_progress,
           GROUP_CONCAT(DISTINCT c.course_code SEPARATOR ', ') as courses
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    WHERE c.instructor_id = '$instructor_id' AND u.role = 'student'
    GROUP BY u.id
    ORDER BY u.full_name ASC
";
$students = fetch_all(query($students_query));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - Skynusa Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f5f7fa; color: #2d3748; }
        
        .dashboard { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px; background: linear-gradient(180deg, #2c5282 0%, #2b6cb0 100%);
            color: white; padding: 0; position: fixed; height: 100vh; overflow-y: auto;
        }
        .sidebar-header { padding: 30px 25px; background: rgba(255,255,255,0.08); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; font-weight: 700; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 5px; }
        
        .instructor-profile { padding: 25px; background: rgba(255,255,255,0.05); margin: 20px; border-radius: 12px; }
        .instructor-profile .avatar {
            width: 60px; height: 60px; background: linear-gradient(135deg, #4299e1, #667eea);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 26px; font-weight: 700; margin-bottom: 12px; border: 3px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-menu { list-style: none; padding: 20px 0; }
        .sidebar-menu li { margin: 3px 15px; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 14px 15px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; border-radius: 10px; font-size: 14px;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; transform: translateX(5px); }
        .sidebar-menu a.active { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a span:first-child { margin-right: 12px; font-size: 18px; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; }
        
        .header {
            background: white; padding: 25px 30px; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; font-weight: 700; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;
        }
        
        .students-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;
        }
        
        .student-card {
            background: white; border: 2px solid #e2e8f0; border-radius: 12px;
            padding: 20px; transition: all 0.3s;
        }
        .student-card:hover {
            border-color: #4299e1; box-shadow: 0 5px 15px rgba(66, 153, 225, 0.2);
            transform: translateY(-3px);
        }
        
        .student-header {
            display: flex; align-items: center; margin-bottom: 15px;
        }
        .student-avatar {
            width: 50px; height: 50px; background: linear-gradient(135deg, #4299e1, #667eea);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: white; margin-right: 15px;
        }
        .student-info h3 { font-size: 16px; margin-bottom: 3px; }
        .student-info p { font-size: 13px; color: #64748b; }
        
        .student-stats {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
            margin-bottom: 15px; padding: 15px; background: #f7fafc; border-radius: 8px;
        }
        .stat-item {
            display: flex; flex-direction: column; gap: 4px;
        }
        .stat-item .label { font-size: 12px; color: #64748b; }
        .stat-item .value { font-size: 18px; font-weight: 700; }
        
        .student-courses {
            font-size: 13px; color: #4a5568; line-height: 1.6;
            padding: 10px; background: #f9fafb; border-radius: 6px;
        }
        
        .progress-bar {
            background: #e2e8f0; height: 8px; border-radius: 10px; overflow: hidden; margin-top: 8px;
        }
        .progress-fill {
            height: 100%; background: linear-gradient(90deg, #4299e1, #667eea);
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .students-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_instructor.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>👥 My Students</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <div class="panel">
                <h2 style="margin-bottom: 20px;">📋 Students in My Courses (<?php echo count($students); ?>)</h2>
                
                <?php if (empty($students)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                        <div style="font-size: 48px; margin-bottom: 15px;">👥</div>
                        <h3>No Students Yet</h3>
                        <p>You don't have any students enrolled in your courses yet</p>
                    </div>
                <?php else: ?>
                    <div class="students-grid">
                        <?php foreach ($students as $student): ?>
                            <div class="student-card">
                                <div class="student-header">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                    </div>
                                    <div class="student-info">
                                        <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($student['email']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="student-stats">
                                    <div class="stat-item">
                                        <span class="label">📚 Courses</span>
                                        <span class="value"><?php echo $student['enrolled_courses']; ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="label">📊 Avg Progress</span>
                                        <span class="value"><?php echo number_format($student['avg_progress'] ?? 0, 0); ?>%</span>
                                    </div>
                                </div>
                                
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $student['avg_progress'] ?? 0; ?>%"></div>
                                </div>
                                
                                <div class="student-courses" style="margin-top: 15px;">
                                    <strong>Enrolled in:</strong><br>
                                    <?php echo htmlspecialchars($student['courses']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>