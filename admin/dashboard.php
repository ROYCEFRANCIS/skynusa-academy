<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Cek apakah sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_name = $_SESSION['full_name'];

// Stats dummy (nanti bisa ganti dengan real query)
$stats = [
    'total_students' => 156,
    'total_instructors' => 12,
    'total_courses' => 24,
    'active_courses' => 18
];

// Recent activities
$recent_activities = [
    ['type' => 'enrollment', 'user' => 'John Doe', 'action' => 'enrolled in Web Development', 'time' => '5 min ago'],
    ['type' => 'course', 'user' => 'Jane Smith', 'action' => 'created new course UI/UX Design', 'time' => '15 min ago'],
    ['type' => 'user', 'user' => 'Mike Johnson', 'action' => 'registered as new student', 'time' => '1 hour ago'],
    ['type' => 'grade', 'user' => 'Sarah Wilson', 'action' => 'completed Digital Marketing course', 'time' => '2 hours ago'],
    ['type' => 'enrollment', 'user' => 'Tom Brown', 'action' => 'enrolled in Python Programming', 'time' => '3 hours ago']
];

// Top courses
$top_courses = [
    ['name' => 'Web Development Fundamentals', 'students' => 45, 'instructor' => 'John Doe', 'rating' => 4.8],
    ['name' => 'UI/UX Design Mastery', 'students' => 38, 'instructor' => 'Jane Smith', 'rating' => 4.9],
    ['name' => 'Digital Marketing Strategy', 'students' => 32, 'instructor' => 'Mike Johnson', 'rating' => 4.7],
    ['name' => 'Python Programming', 'students' => 28, 'instructor' => 'Sarah Lee', 'rating' => 4.6],
    ['name' => 'Mobile App Development', 'students' => 25, 'instructor' => 'Tom Wilson', 'rating' => 4.8]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Skynusa Academy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 24px rgba(0,0,0,0.12);
        }

        .sidebar-header {
            padding: 30px 25px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-header p {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .admin-profile {
            padding: 25px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            margin: 20px;
            border-radius: 12px;
        }

        .admin-profile .avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .admin-profile h3 {
            font-size: 17px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .admin-profile p {
            font-size: 13px;
            color: rgba(255,255,255,0.8);
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin: 3px 15px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 14px 15px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            border-radius: 10px;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.08);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .sidebar-menu a span:first-child {
            margin-right: 12px;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar h1 {
            font-size: 28px;
            color: #0f172a;
            font-weight: 700;
        }

        .top-bar-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
        .stat-icon.green { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
        .stat-icon.purple { background: linear-gradient(135deg, #e9d5ff, #d8b4fe); }
        .stat-icon.orange { background: linear-gradient(135deg, #fed7aa, #fdba74); }

        .stat-card h3 {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .stat-trend {
            font-size: 13px;
            color: #10b981;
            font-weight: 600;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .panel {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .panel-header h2 {
            font-size: 20px;
            color: #0f172a;
            font-weight: 700;
        }

        .panel-header a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: start;
            gap: 15px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .activity-icon.enrollment { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
        .activity-icon.course { background: linear-gradient(135deg, #e9d5ff, #d8b4fe); }
        .activity-icon.user { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
        .activity-icon.grade { background: linear-gradient(135deg, #fed7aa, #fdba74); }

        .activity-content h4 {
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .activity-content p {
            font-size: 13px;
            color: #64748b;
        }

        .activity-time {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* Course Table */
        .course-list {
            list-style: none;
        }

        .course-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 12px;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .course-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        .course-item h4 {
            font-size: 15px;
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .course-item p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .course-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #94a3b8;
        }

        .rating {
            color: #f59e0b;
            font-weight: 600;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar-header h2,
            .sidebar-header p,
            .admin-profile h3,
            .admin-profile p,
            .sidebar-menu span:last-child {
                display: none;
            }

            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>⚡ SKYNUSA</h2>
                <p>Admin Panel</p>
            </div>

            <div class="admin-profile">
                <div class="avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                <h3><?php echo $admin_name; ?></h3>
                <p>System Administrator</p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><span>📊</span> <span>Dashboard</span></a></li>
                <li><a href="#"><span>👥</span> <span>Users</span></a></li>
                <li><a href="#"><span>👨‍🎓</span> <span>Students</span></a></li>
                <li><a href="#"><span>👨‍🏫</span> <span>Instructors</span></a></li>
                <li><a href="#"><span>📚</span> <span>Courses</span></a></li>
                <li><a href="#"><span>📅</span> <span>Schedules</span></a></li>
                <li><a href="#"><span>📄</span> <span>Materials</span></a></li>
                <li><a href="#"><span>📝</span> <span>Enrollments</span></a></li>
                <li><a href="#"><span>⭐</span> <span>Evaluations</span></a></li>
                <li><a href="#"><span>⚙️</span> <span>Settings</span></a></li>
                <li><a href="../logout.php"><span>🚪</span> <span>Logout</span></a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div>
                    <h1>🎯 Admin Dashboard</h1>
                </div>
                <div class="top-bar-actions">
                    <a href="#" class="btn btn-secondary">📊 Reports</a>
                    <a href="#" class="btn btn-primary">➕ Add New</a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <h3>Total Students</h3>
                            <div class="number"><?php echo $stats['total_students']; ?></div>
                            <div class="stat-trend">↗ +12% dari bulan lalu</div>
                        </div>
                        <div class="stat-icon blue">👨‍🎓</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <h3>Total Instructors</h3>
                            <div class="number"><?php echo $stats['total_instructors']; ?></div>
                            <div class="stat-trend">↗ +3 instructor baru</div>
                        </div>
                        <div class="stat-icon green">👨‍🏫</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <h3>Total Courses</h3>
                            <div class="number"><?php echo $stats['total_courses']; ?></div>
                            <div class="stat-trend">↗ +5 courses baru</div>
                        </div>
                        <div class="stat-icon purple">📚</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <h3>Active Courses</h3>
                            <div class="number"><?php echo $stats['active_courses']; ?></div>
                            <div class="stat-trend">✅ Semester aktif</div>
                        </div>
                        <div class="stat-icon orange">🔥</div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Activities -->
                <div class="panel">
                    <div class="panel-header">
                        <h2>📋 Recent Activities</h2>
                        <a href="#">View All →</a>
                    </div>
                    <ul class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon <?php echo $activity['type']; ?>">
                                    <?php 
                                    $icons = ['enrollment' => '📝', 'course' => '📚', 'user' => '👤', 'grade' => '⭐'];
                                    echo $icons[$activity['type']];
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo $activity['user']; ?></h4>
                                    <p><?php echo $activity['action']; ?></p>
                                    <div class="activity-time"><?php echo $activity['time']; ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Top Courses -->
                <div class="panel">
                    <div class="panel-header">
                        <h2>🏆 Top Courses</h2>
                        <a href="#">View All →</a>
                    </div>
                    <ul class="course-list">
                        <?php foreach (array_slice($top_courses, 0, 5) as $course): ?>
                            <li class="course-item">
                                <div class="course-header">
                                    <div>
                                        <h4><?php echo $course['name']; ?></h4>
                                        <p>By <?php echo $course['instructor']; ?></p>
                                    </div>
                                </div>
                                <div class="course-meta">
                                    <span>👥 <?php echo $course['students']; ?> students</span>
                                    <span class="rating">⭐ <?php echo $course['rating']; ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>