<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_name = $_SESSION['full_name'];

// Get comprehensive statistics
$total_users = mysqli_num_rows(query("SELECT * FROM users"));
$total_courses = mysqli_num_rows(query("SELECT * FROM courses"));
$total_enrollments = mysqli_num_rows(query("SELECT * FROM enrollments"));
$active_students = mysqli_num_rows(query("SELECT * FROM users WHERE role='student' AND status='active'"));
$active_instructors = mysqli_num_rows(query("SELECT * FROM users WHERE role='instructor' AND status='active'"));
$completed_courses = mysqli_num_rows(query("SELECT * FROM enrollments WHERE status='completed'"));

// Course popularity
$popular_courses = fetch_all(query("
    SELECT c.course_name, c.course_code, COUNT(e.id) as enrollment_count
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id
    GROUP BY c.id
    ORDER BY enrollment_count DESC
    LIMIT 10
"));

// Instructor performance
$instructor_stats = fetch_all(query("
    SELECT u.full_name, 
           COUNT(DISTINCT c.id) as total_courses,
           COUNT(DISTINCT e.id) as total_students,
           AVG(e.progress) as avg_progress
    FROM users u
    LEFT JOIN courses c ON u.id = c.instructor_id
    LEFT JOIN enrollments e ON c.id = e.course_id
    WHERE u.role = 'instructor'
    GROUP BY u.id
    ORDER BY total_students DESC
"));

// Monthly enrollment trends (last 6 months)
$enrollment_trends = fetch_all(query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as count
    FROM enrollments
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            padding: 30px 0; overflow-y: auto; z-index: 1000;
        }
        
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
        
        .header {
            background: white; padding: 25px 30px; border-radius: 15px;
            margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; font-weight: 700; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
            font-weight: 600; text-decoration: none; display: inline-block;
            transition: all 0.3s; font-size: 14px;
        }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; }
        
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        
        .stat-card {
            background: white; padding: 20px; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #3b82f6;
        }
        .stat-label { color: #64748b; font-size: 13px; margin-bottom: 5px; }
        .stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;
        }
        .panel h2 { margin-bottom: 20px; font-size: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc; padding: 15px; text-align: left;
            font-weight: 600; font-size: 13px; color: #475569;
            text-transform: uppercase;
        }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_admin.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>📊 Reports & Analytics</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-label">Total Courses</div>
                <div class="stat-value"><?php echo $total_courses; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-label">Total Enrollments</div>
                <div class="stat-value"><?php echo $total_enrollments; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #8b5cf6;">
                <div class="stat-label">Active Students</div>
                <div class="stat-value"><?php echo $active_students; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ef4444;">
                <div class="stat-label">Active Instructors</div>
                <div class="stat-value"><?php echo $active_instructors; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #06b6d4;">
                <div class="stat-label">Completed Courses</div>
                <div class="stat-value"><?php echo $completed_courses; ?></div>
            </div>
        </div>
        
        <div class="panel">
            <h2>🏆 Most Popular Courses</h2>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Total Enrollments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($popular_courses as $course): 
                    ?>
                        <tr>
                            <td><strong>#<?php echo $rank++; ?></strong></td>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><strong><?php echo $course['enrollment_count']; ?></strong> students</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="panel">
            <h2>👨‍🏫 Instructor Performance</h2>
            <table>
                <thead>
                    <tr>
                        <th>Instructor</th>
                        <th>Total Courses</th>
                        <th>Total Students</th>
                        <th>Avg Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instructor_stats as $instructor): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($instructor['full_name']); ?></strong></td>
                            <td><?php echo $instructor['total_courses']; ?></td>
                            <td><?php echo $instructor['total_students']; ?></td>
                            <td><?php echo number_format($instructor['avg_progress'] ?? 0, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="panel">
            <h2>📈 Enrollment Trends (Last 6 Months)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>New Enrollments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollment_trends as $trend): ?>
                        <tr>
                            <td><strong><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></strong></td>
                            <td><?php echo $trend['count']; ?> enrollments</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>