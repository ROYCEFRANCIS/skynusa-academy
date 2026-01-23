<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Get student's enrolled courses
$my_courses = $conn->query("
    SELECT c.*, e.progress, e.status as enrollment_status, e.final_grade,
           u.full_name as instructor_name,
           (SELECT MIN(schedule_date) FROM schedules WHERE course_id = c.id AND schedule_date > CURDATE()) as next_class,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count,
           (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as assignment_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.instructor_id = u.id
    WHERE e.student_id = '$student_id'
    ORDER BY e.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <title>My Courses - Skynusa Academy</title>
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
        
        .courses-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 25px;
        }
        
        .course-card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;
        }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        
        .course-header {
            display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;
        }
        .course-category {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        
        .course-card h3 { font-size: 18px; margin-bottom: 8px; color: #1a1a1a; }
        .course-card .instructor {
            color: #6b7280; font-size: 14px; margin-bottom: 15px;
        }
        
        .progress-section {
            margin-bottom: 20px;
        }
        .progress-label {
            display: flex; justify-content: space-between; margin-bottom: 8px;
            font-size: 14px; color: #4b5563; font-weight: 600;
        }
        .progress-bar {
            background: #e5e7eb; height: 8px; border-radius: 10px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s;
        }
        
        .course-stats {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
            margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 8px;
        }
        .stat-item {
            display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6b7280;
        }
        
        .course-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 15px; border-top: 1px solid #e5e7eb;
        }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-completed { background: #dbeafe; color: #1e40af; }
        .badge-dropped { background: #fee2e2; color: #991b1b; }
        
        .empty-state {
            text-align: center; padding: 80px 20px; background: white;
            border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .courses-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>📚 My Courses</h1>
                <a href="browse_courses.php" class="btn btn-primary">🔍 Browse More Courses</a>
            </header>
            
            <?php if ($my_courses->num_rows > 0): ?>
                <div class="courses-grid">
                    <?php while ($course = $my_courses->fetch_assoc()): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                    <p class="instructor">👨‍🏫 <?php echo htmlspecialchars($course['instructor_name']); ?></p>
                                </div>
                                <span class="course-category"><?php echo htmlspecialchars($course['category']); ?></span>
                            </div>
                            
                            <div class="progress-section">
                                <div class="progress-label">
                                    <span>Progress</span>
                                    <span><?php echo number_format($course['progress'], 0); ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="course-stats">
                                <div class="stat-item">
                                    <span>📄</span>
                                    <span><?php echo $course['material_count']; ?> Materials</span>
                                </div>
                                <div class="stat-item">
                                    <span>📝</span>
                                    <span><?php echo $course['assignment_count']; ?> Assignments</span>
                                </div>
                                <?php if ($course['next_class']): ?>
                                    <div class="stat-item" style="grid-column: 1 / -1;">
                                        <span>📅</span>
                                        <span>Next: <?php echo date('d M Y', strtotime($course['next_class'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($course['final_grade']): ?>
                                    <div class="stat-item" style="grid-column: 1 / -1;">
                                        <span>⭐</span>
                                        <span>Grade: <?php echo number_format($course['final_grade'], 1); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="course-footer">
                                <span class="badge badge-<?php echo $course['enrollment_status']; ?>">
                                    <?php echo ucfirst($course['enrollment_status']); ?>
                                </span>
                                <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                    <?php echo $course['enrollment_status'] == 'completed' ? 'Review' : 'Continue'; ?> →
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <h2>Belum Ada Kursus</h2>
                    <p>Anda belum mendaftar ke kursus apapun</p>
                    <br>
                    <a href="browse_courses.php" class="btn btn-primary">Browse Courses</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>