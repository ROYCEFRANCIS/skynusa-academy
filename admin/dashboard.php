<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_name = $_SESSION['full_name'];

// Get statistics
$total_courses = mysqli_num_rows(query("SELECT * FROM courses"));
$total_students = mysqli_num_rows(query("SELECT * FROM users WHERE role='student' AND status='active'"));
$total_instructors = mysqli_num_rows(query("SELECT * FROM users WHERE role='instructor' AND status='active'"));
$total_enrollments = mysqli_num_rows(query("SELECT * FROM enrollments WHERE status='active'"));

// Get recent enrollments
$recent_enrollments = fetch_all(query("
    SELECT e.*, u.full_name as student_name, c.course_name, c.course_code
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY e.created_at DESC
    LIMIT 5
"));

// Get active courses
$active_courses = fetch_all(query("
    SELECT c.*, u.full_name as instructor_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status='active') as enrolled
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    WHERE c.status='active'
    ORDER BY enrolled DESC
    LIMIT 5
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Skynusa Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background: #f8fafc; 
            color: #1e293b;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            padding: 30px 0;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .logo {
            padding: 0 30px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo h2 {
            color: white;
            font-size: 24px;
            font-weight: 700;
        }
        
        .logo p {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 30px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 4px solid #3b82f6;
        }
        
        .nav-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
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
            font-weight: 700; 
        }
        
        .user-info {
            display: flex;
            align-items: center;
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
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
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
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
            color: white;
        }
        
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
        .stat-card h3 {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
        }
        
        /* Panel */
        .panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .panel-header h2 {
            font-size: 20px;
            color: #1e293b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
            text-transform: uppercase;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .logo h2,
            .sidebar .logo p,
            .nav-item span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>🎓 SKYNUSA</h2>
            <p>Academy Admin</p>
        </div>
        
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="courses.php" class="nav-item">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
            <a href="students.php" class="nav-item">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a href="instructors.php" class="nav-item">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Instructors</span>
            </a>
            <a href="enrollments.php" class="nav-item">
                <i class="fas fa-clipboard-list"></i>
                <span>Enrollments</span>
            </a>
            <a href="../logout.php" class="nav-item" style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>👋 Welcome, <?php echo $admin_name; ?></h1>
            <div class="user-info">
                <span>Admin Panel</span>
                <a href="../logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📚</div>
                <h3>Total Courses</h3>
                <div class="number"><?php echo $total_courses; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">👨‍🎓</div>
                <h3>Total Students</h3>
                <div class="number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">👨‍🏫</div>
                <h3>Total Instructors</h3>
                <div class="number"><?php echo $total_instructors; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">📝</div>
                <h3>Total Enrollments</h3>
                <div class="number"><?php echo $total_enrollments; ?></div>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-header">
                <h2>📊 Active Courses</h2>
                <a href="courses.php" class="btn btn-primary">View All</a>
            </div>
            <?php if (empty($active_courses)): ?>
                <div class="empty-state">
                    📭 No active courses yet
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Name</th>
                            <th>Instructor</th>
                            <th>Enrolled</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_courses as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['instructor_name']); ?></td>
                                <td><span class="badge badge-info"><?php echo $course['enrolled']; ?> students</span></td>
                                <td>
                                    <a href="courses.php?edit=<?php echo $course['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="panel">
            <div class="panel-header">
                <h2>🎓 Recent Enrollments</h2>
                <a href="enrollments.php" class="btn btn-primary">View All</a>
            </div>
            <?php if (empty($recent_enrollments)): ?>
                <div class="empty-state">
                    📭 No enrollments yet
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Enrolled Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_enrollments as $enrollment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($enrollment['student_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($enrollment['course_name']); ?></strong></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($enrollment['status']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($enrollment['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html><link rel="stylesheet" href="../assets/css/enhanced-style.css">