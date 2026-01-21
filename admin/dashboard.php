<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$user_name = $_SESSION['full_name'];

// Get all statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student'")->fetch_assoc()['count'];
$total_instructors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='instructor'")->fetch_assoc()['count'];
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$active_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status='active'")->fetch_assoc()['count'];
$total_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='active'")->fetch_assoc()['count'];

// Recent enrollments
$recent_enrollments = $conn->query("
    SELECT e.*, u.full_name as student_name, c.course_name, c.course_code
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY e.enrollment_date DESC
    LIMIT 5
");

// Top courses by enrollment
$top_courses = $conn->query("
    SELECT c.course_name, c.course_code, c.quota,
           COUNT(e.id) as enrolled_count
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status='active'
    WHERE c.status='active'
    GROUP BY c.id
    ORDER BY enrolled_count DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/enhanced-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 280px;
            background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            padding: 30px 0; overflow-y: auto; z-index: 1000;
        }
        .logo { padding: 0 30px 25px; border-bottom: 2px solid #f1f5f9; }
        .logo h2 {
            font-size: 26px; font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }
        .logo p { color: #64748b; font-size: 13px; font-weight: 500; }
        .nav-menu { padding: 20px 0; }
        .nav-item {
            display: flex; align-items: center; padding: 14px 30px;
            color: #475569; text-decoration: none; font-weight: 500;
            font-size: 15px; transition: all 0.3s ease; position: relative;
        }
        .nav-item::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 4px; background: linear-gradient(135deg, #667eea, #764ba2);
            transform: scaleY(0); transition: transform 0.3s ease;
        }
        .nav-item:hover, .nav-item.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.08), transparent);
            color: #667eea;
        }
        .nav-item.active::before { transform: scaleY(1); }
        .nav-item i { width: 24px; margin-right: 15px; font-size: 18px; }
        .main-content { margin-left: 280px; padding: 30px; min-height: 100vh; }
        .header {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px 30px; margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .header h1 { font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 5px; }
        .header p { color: #64748b; font-size: 15px; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px; margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease; position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; right: 0;
            width: 100px; height: 100px; background: currentColor;
            opacity: 0.05; border-radius: 50%; transform: translate(30%, -30%);
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12); }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: white; margin-bottom: 15px;
        }
        .stat-label { font-size: 14px; color: #64748b; font-weight: 500; margin-bottom: 8px; }
        .stat-value { font-size: 36px; font-weight: 800; color: #1e293b; }
        .panel {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); margin-bottom: 30px;
        }
        .panel h2 { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        thead tr { background: #f8fafc; }
        th {
            padding: 15px; text-align: left; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        th:first-child { border-top-left-radius: 12px; }
        th:last-child { border-top-right-radius: 12px; }
        td { padding: 18px 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        tbody tr { transition: background 0.2s; }
        tbody tr:hover { background: #f8fafc; }
        .progress-bar-container {
            width: 100%; height: 8px; background: #e2e8f0;
            border-radius: 10px; overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%; background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px; transition: width 0.3s ease;
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar .logo h2, .sidebar .logo p, .nav-item span { display: none; }
            .main-content { margin-left: 70px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>🎓 SKYNUSA</h2>
            <p>Admin Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <a href="courses.php" class="nav-item">
                <i class="fas fa-book"></i><span>Courses</span>
            </a>
            <a href="students.php" class="nav-item">
                <i class="fas fa-user-graduate"></i><span>Students</span>
            </a>
            <a href="instructors.php" class="nav-item">
                <i class="fas fa-chalkboard-teacher"></i><span>Instructors</span>
            </a>
            <a href="enrollments.php" class="nav-item">
                <i class="fas fa-clipboard-list"></i><span>Enrollments</span>
            </a>
            <a href="../logout.php" class="nav-item" style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>👋 Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p>Here's what's happening with your academy today</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card" style="color: #667eea;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-label">Total Students</div>
                <div class="stat-value"><?php echo number_format($total_students); ?></div>
            </div>
            
            <div class="stat-card" style="color: #10b981;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-label">Total Instructors</div>
                <div class="stat-value"><?php echo number_format($total_instructors); ?></div>
            </div>
            
            <div class="stat-card" style="color: #f59e0b;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-label">Total Courses</div>
                <div class="stat-value"><?php echo number_format($total_courses); ?></div>
            </div>
            
            <div class="stat-card" style="color: #ef4444;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-label">Active Enrollments</div>
                <div class="stat-value"><?php echo number_format($total_enrollments); ?></div>
            </div>
        </div>
        
        <div class="panel">
            <h2>📋 Recent Enrollments</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($enroll = $recent_enrollments->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($enroll['student_name']); ?></strong></td>
                        <td>
                            <span style="color: #667eea; font-weight: 600;"><?php echo htmlspecialchars($enroll['course_code']); ?></span>
                            - <?php echo htmlspecialchars($enroll['course_name']); ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($enroll['enrollment_date'])); ?></td>
                        <td><span class="badge badge-success badge-dot">Active</span></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($recent_enrollments->num_rows == 0): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">📝 No recent enrollments</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="panel">
            <h2>🔥 Top Courses by Enrollment</h2>
            <table>
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Enrolled</th>
                        <th>Quota</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($course = $top_courses->fetch_assoc()): 
                        $percentage = $course['quota'] > 0 ? ($course['enrolled_count'] / $course['quota']) * 100 : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                        <td><?php echo $course['enrolled_count']; ?></td>
                        <td><?php echo $course['quota']; ?></td>
                        <td style="width: 200px;">
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($top_courses->num_rows == 0): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">📚 No courses available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="../assets/js/enhanced-ui.js"></script>
    <script>
        setTimeout(() => {
            toast.info('Welcome to your dashboard! 🎓');
        }, 500);
    </script>
</body>
</html>