<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../index.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['full_name'];

// Get all courses with statistics
$courses_query = "
    SELECT c.*, 
           COUNT(DISTINCT e.id) as total_students,
           COUNT(DISTINCT m.id) as total_materials,
           COUNT(DISTINCT s.id) as total_schedules,
           AVG(e.progress) as avg_progress
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status='active'
    LEFT JOIN materials m ON c.id = m.course_id
    LEFT JOIN schedules s ON c.id = s.course_id
    WHERE c.instructor_id='$instructor_id'
    GROUP BY c.id
    ORDER BY c.created_at DESC
";
$courses = fetch_all(query($courses_query));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Skynusa Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f5f7fa; color: #2d3748; }
        
        .dashboard { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px; background: linear-gradient(180deg, #2c5282 0%, #2b6cb0 100%);
            color: white; padding: 0; position: fixed; height: 100vh; overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar-header { padding: 30px 25px; background: rgba(255,255,255,0.08); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.7); }
        
        .instructor-profile {
            padding: 25px; background: rgba(255,255,255,0.05); margin: 20px; border-radius: 12px;
        }
        .instructor-profile .avatar {
            width: 60px; height: 60px; background: linear-gradient(135deg, #4299e1, #667eea);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 26px; font-weight: 700; margin-bottom: 12px; border: 3px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-menu { list-style: none; padding: 20px 0; }
        .sidebar-menu li { margin: 3px 15px; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 14px 15px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; font-size: 14px; border-radius: 10px;
            font-weight: 500;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; transform: translateX(5px); }
        .sidebar-menu a.active { background: rgba(255,255,255,0.15); color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .sidebar-menu a span:first-child { margin-right: 12px; font-size: 18px; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; }
        
        .header {
            background: white; padding: 25px 30px; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 28px; color: #2d3748; font-weight: 700; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
            font-weight: 600; text-decoration: none; display: inline-block;
            transition: all 0.3s; font-size: 14px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4299e1, #667eea); color: white;
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4); }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .courses-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px;
        }
        
        .course-card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;
            border-left: 4px solid #4299e1;
        }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        
        .course-header {
            display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;
        }
        .course-code {
            background: linear-gradient(135deg, #4299e1, #667eea); color: white;
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        
        .course-card h3 { font-size: 20px; margin-bottom: 10px; color: #2d3748; }
        .course-card p { color: #718096; font-size: 14px; margin-bottom: 20px; line-height: 1.6; }
        
        .course-stats {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;
            margin-bottom: 20px; padding: 15px; background: #f7fafc; border-radius: 10px;
        }
        .stat-item {
            display: flex; flex-direction: column; gap: 5px;
        }
        .stat-item .label { font-size: 12px; color: #718096; font-weight: 500; }
        .stat-item .value { font-size: 20px; font-weight: 700; color: #2d3748; }
        
        .course-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 20px; border-top: 1px solid #e2e8f0; gap: 10px;
        }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-danger { background: #fed7d7; color: #742a2a; }
        
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
<?php include 'sidebar_instructor.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>📚 Kursus yang Saya Ampu</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <h2>Belum Ada Kursus</h2>
                    <p>Anda belum ditugaskan untuk mengampu kursus apapun</p>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                <span class="badge <?php echo $course['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </div>
                            
                            <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($course['description'], 0, 120)) . '...'; ?></p>
                            
                            <div class="course-stats">
                                <div class="stat-item">
                                    <span class="label">👨‍🎓 Peserta</span>
                                    <span class="value"><?php echo $course['total_students']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="label">📄 Materi</span>
                                    <span class="value"><?php echo $course['total_materials']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="label">📅 Jadwal</span>
                                    <span class="value"><?php echo $course['total_schedules']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="label">📊 Rata-rata Progress</span>
                                    <span class="value"><?php echo number_format($course['avg_progress'] ?? 0, 0); ?>%</span>
                                </div>
                            </div>
                            
                            <div class="course-footer">
                                <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                    Detail Kursus →
                                </a>
                                <a href="materials.php?course_id=<?php echo $course['id']; ?>" class="btn btn-secondary btn-sm">
                                    Kelola Materi
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>