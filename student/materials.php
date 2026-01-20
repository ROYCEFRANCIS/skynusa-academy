<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Filter by course
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_sql = $course_filter > 0 ? " AND m.course_id = $course_filter" : "";

// Get materials from enrolled courses
$materials = $conn->query("
    SELECT m.*, c.course_name, c.course_code
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = '$student_id' $filter_sql
    ORDER BY m.created_at DESC
");

// Get enrolled courses for filter
$enrolled_courses = $conn->query("
    SELECT DISTINCT c.id, c.course_code, c.course_name
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = '$student_id'
    ORDER BY c.course_name
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materials - Skynusa Academy</title>
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
        .btn-secondary { background: #e5e7eb; color: #4b5563; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .filter-bar {
            display: flex; gap: 15px; align-items: center; margin-bottom: 30px;
            padding: 20px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filter-bar select {
            padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px;
            font-size: 14px; min-width: 250px; cursor: pointer;
        }
        .filter-bar select:focus { outline: none; border-color: #667eea; }
        
        .materials-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;
        }
        
        .material-card {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;
            border-left: 4px solid #667eea;
        }
        .material-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        
        .material-icon {
            width: 50px; height: 50px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 15px;
        }
        .material-icon.pdf { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .material-icon.video { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .material-icon.link { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .material-icon.document { background: linear-gradient(135deg, #10b981, #059669); }
        .material-icon.other { background: linear-gradient(135deg, #6b7280, #4b5563); }
        
        .material-card h3 { font-size: 18px; margin-bottom: 8px; color: #1a1a1a; }
        .material-card .course {
            color: #6b7280; font-size: 13px; margin-bottom: 12px;
            padding: 4px 10px; background: #f3f4f6; border-radius: 12px; display: inline-block;
        }
        .material-card .description {
            color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 15px;
        }
        .material-meta {
            font-size: 12px; color: #9ca3af; padding-top: 12px; border-top: 1px solid #e5e7eb;
        }
        
        .badge {
            padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;
            display: inline-block; margin-right: 8px;
        }
        .badge-pdf { background: #fee2e2; color: #991b1b; }
        .badge-video { background: #ede9fe; color: #5b21b6; }
        .badge-link { background: #dbeafe; color: #1e40af; }
        .badge-document { background: #d1fae5; color: #065f46; }
        .badge-other { background: #e5e7eb; color: #4b5563; }
        
        .empty-state {
            text-align: center; padding: 80px 20px; background: white;
            border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .materials-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
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
                <li><a href="dashboard.php"><span>📊</span> <span>Dashboard</span></a></li>
                <li><a href="my_courses.php"><span>📚</span> <span>My Courses</span></a></li>
                <li><a href="schedule.php"><span>📅</span> <span>Schedule</span></a></li>
                <li><a href="assignments.php"><span>📝</span> <span>Assignments</span></a></li>
                <li><a href="grades.php"><span>⭐</span> <span>Grades</span></a></li>
                <li><a href="materials.php" class="active"><span>📄</span> <span>Materials</span></a></li>
                <li><a href="messages.php"><span>💬</span> <span>Messages</span></a></li>
                <li><a href="../logout.php"><span>🚪</span> <span>Logout</span></a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <header class="header">
                <h1>📄 Course Materials</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <div class="filter-bar">
                <label style="font-weight: 600; color: #374151;">Filter by Course:</label>
                <select onchange="window.location.href='materials.php?course_id='+this.value">
                    <option value="0">All Courses</option>
                    <?php while ($course = $enrolled_courses->fetch_assoc()): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <?php if ($materials->num_rows > 0): ?>
                <div class="materials-grid">
                    <?php while ($material = $materials->fetch_assoc()): ?>
                        <div class="material-card">
                            <div class="material-icon <?php echo $material['material_type']; ?>">
                                <?php
                                $icons = [
                                    'pdf' => '📄',
                                    'video' => '🎥',
                                    'link' => '🔗',
                                    'document' => '📝',
                                    'other' => '📋'
                                ];
                                echo $icons[$material['material_type']] ?? '📋';
                                ?>
                            </div>
                            
                            <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                            <span class="course">
                                <?php echo htmlspecialchars($material['course_code']); ?> - 
                                <?php echo htmlspecialchars($material['course_name']); ?>
                            </span>
                            
                            <?php if ($material['description']): ?>
                                <p class="description">
                                    <?php echo htmlspecialchars(substr($material['description'], 0, 120)); ?>
                                    <?php echo strlen($material['description']) > 120 ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="margin: 15px 0;">
                                <span class="badge badge-<?php echo $material['material_type']; ?>">
                                    <?php echo ucfirst($material['material_type']); ?>
                                </span>
                            </div>
                            
                            <?php if ($material['file_url']): ?>
                                <a href="<?php echo htmlspecialchars($material['file_url']); ?>" 
                                   target="_blank" class="btn btn-secondary btn-sm" 
                                   style="width: 100%; text-align: center; margin-bottom: 10px;">
                                    🔗 Open Material
                                </a>
                            <?php endif; ?>
                            
                            <div class="material-meta">
                                📅 Added on <?php echo date('d M Y', strtotime($material['created_at'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📄</div>
                    <h2>No Materials Available</h2>
                    <p><?php echo $course_filter > 0 ? 'No materials for this course yet' : 'No materials available in your enrolled courses'; ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>