<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Cek apakah sudah login dan role student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Data dummy untuk demo (nanti bisa diganti dengan real data)
$stats = [
    'enrolled' => 3,
    'completed' => 1,
    'in_progress' => 2,
    'certificates' => 1
];

$my_courses = [
    [
        'id' => 1,
        'name' => 'Web Development Fundamentals',
        'instructor' => 'John Doe',
        'progress' => 75,
        'status' => 'active',
        'next_class' => '2026-01-20',
        'category' => 'Programming'
    ],
    [
        'id' => 2,
        'name' => 'UI/UX Design Mastery',
        'instructor' => 'Jane Smith',
        'progress' => 45,
        'status' => 'active',
        'next_class' => '2026-01-18',
        'category' => 'Design'
    ],
    [
        'id' => 3,
        'name' => 'Digital Marketing Strategy',
        'instructor' => 'Mike Johnson',
        'progress' => 100,
        'status' => 'completed',
        'next_class' => '-',
        'category' => 'Marketing'
    ]
];

$upcoming_schedule = [
    ['course' => 'Web Development', 'time' => '09:00 - 11:00', 'date' => 'Senin, 20 Jan 2026', 'room' => 'Lab A'],
    ['course' => 'UI/UX Design', 'time' => '13:00 - 15:00', 'date' => 'Rabu, 22 Jan 2026', 'room' => 'Lab B'],
    ['course' => 'Digital Marketing', 'time' => '10:00 - 12:00', 'date' => 'Jumat, 24 Jan 2026', 'room' => 'Room 301']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Skynusa Academy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f0f2f5;
            color: #1a1a1a;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e3a8a 0%, #312e81 100%);
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 0 30px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
        }

        .user-profile {
            padding: 25px 30px;
            background: rgba(255,255,255,0.05);
            margin: 20px 0;
        }

        .user-profile .avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .user-profile h3 {
            font-size: 16px;
            margin-bottom: 3px;
        }

        .user-profile p {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin: 5px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 14px 30px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 15px;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 35px;
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left: 4px solid #60a5fa;
        }

        .sidebar-menu a span {
            margin-right: 12px;
            font-size: 18px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            color: #1a1a1a;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #4b5563;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #667eea, #764ba2);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .stat-card h3 {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
        }

        /* Course Cards */
        .courses-section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 22px;
            color: #1a1a1a;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .course-category {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .course-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #1a1a1a;
        }

        .course-card .instructor {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .progress-bar {
            background: #e5e7eb;
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s;
        }

        .progress-text {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 15px;
        }

        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Schedule Table */
        .schedule-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .schedule-table h2 {
            margin-bottom: 20px;
            font-size: 22px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #f9fafb;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar-header h2, 
            .sidebar-header p,
            .user-profile,
            .sidebar-menu a span:last-child {
                display: none;
            }

            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .courses-grid {
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
                <h2>🎓 Skynusa Academy</h2>
                <p>Student Portal</p>
            </div>

            <div class="user-profile">
                <div class="avatar"><?php echo strtoupper(substr($student_name, 0, 1)); ?></div>
                <h3><?php echo $student_name; ?></h3>
                <p>Student</p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><span>📊</span> <span>Dashboard</span></a></li>
                <li><a href="#"><span>📚</span> <span>My Courses</span></a></li>
                <li><a href="#"><span>📅</span> <span>Schedule</span></a></li>
                <li><a href="#"><span>📝</span> <span>Assignments</span></a></li>
                <li><a href="#"><span>⭐</span> <span>Grades</span></a></li>
                <li><a href="#"><span>📄</span> <span>Materials</span></a></li>
                <li><a href="#"><span>💬</span> <span>Messages</span></a></li>
                <li><a href="../logout.php"><span>🚪</span> <span>Logout</span></a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div>
                    <h1>👋 Welcome back, <?php echo explode(' ', $student_name)[0]; ?>!</h1>
                </div>
                <div class="header-actions">
                    <a href="#" class="btn btn-secondary">🔔 Notifications</a>
                    <a href="../logout.php" class="btn btn-primary">Logout</a>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">📚</div>
                    <h3>Enrolled Courses</h3>
                    <div class="number"><?php echo $stats['enrolled']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <h3>Completed</h3>
                    <div class="number"><?php echo $stats['completed']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">📖</div>
                    <h3>In Progress</h3>
                    <div class="number"><?php echo $stats['in_progress']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">🏆</div>
                    <h3>Certificates</h3>
                    <div class="number"><?php echo $stats['certificates']; ?></div>
                </div>
            </div>

            <!-- My Courses -->
            <div class="courses-section">
                <div class="section-header">
                    <h2>📚 My Courses</h2>
                    <a href="#" class="btn btn-primary">Browse All Courses</a>
                </div>
                <div class="courses-grid">
                    <?php foreach ($my_courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div>
                                    <h3><?php echo $course['name']; ?></h3>
                                    <p class="instructor">👨‍🏫 <?php echo $course['instructor']; ?></p>
                                </div>
                                <span class="course-category"><?php echo $course['category']; ?></span>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                            </div>
                            <p class="progress-text"><?php echo $course['progress']; ?>% Complete</p>
                            
                            <div class="course-footer">
                                <span class="badge <?php echo $course['status'] == 'completed' ? 'badge-completed' : 'badge-success'; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                                <a href="#" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;">
                                    <?php echo $course['status'] == 'completed' ? 'Review' : 'Continue'; ?> →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Upcoming Schedule -->
            <div class="schedule-table">
                <h2>📅 Upcoming Schedule</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Room</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_schedule as $schedule): ?>
                            <tr>
                                <td><strong><?php echo $schedule['course']; ?></strong></td>
                                <td><?php echo $schedule['date']; ?></td>
                                <td><?php echo $schedule['time']; ?></td>
                                <td><?php echo $schedule['room']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>