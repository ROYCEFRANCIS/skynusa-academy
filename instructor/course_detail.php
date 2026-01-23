<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../index.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['full_name'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify course belongs to instructor
$course_query = "
    SELECT c.*, 
           COUNT(DISTINCT e.id) as total_students,
           AVG(e.progress) as avg_progress
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status='active'
    WHERE c.id = '$course_id' AND c.instructor_id = '$instructor_id'
    GROUP BY c.id
";
$course_result = query($course_query);
$course = fetch_one($course_result);

if (!$course) {
    header("Location: my_courses.php");
    exit();
}

// Get enrolled students
$students = fetch_all(query("
    SELECT u.*, e.progress, e.status, e.final_grade, e.created_at as enrolled_at
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    WHERE e.course_id = '$course_id'
    ORDER BY e.created_at DESC
"));

// Get materials
$materials = fetch_all(query("
    SELECT * FROM materials 
    WHERE course_id = '$course_id' 
    ORDER BY created_at DESC
"));

// Get schedules
$schedules = fetch_all(query("
    SELECT * FROM schedules 
    WHERE course_id = '$course_id' 
    ORDER BY schedule_date DESC, start_time DESC
    LIMIT 10
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <title><?php echo htmlspecialchars($course['course_name']); ?> - Skynusa Academy</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .header-top { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; }
        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .header .course-code {
            background: linear-gradient(135deg, #4299e1, #667eea); color: white;
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, #4299e1, #667eea); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4); }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px; margin-bottom: 20px;
        }
        .stat-box {
            background: linear-gradient(135deg, #4299e1, #667eea);
            color: white; padding: 20px; border-radius: 12px;
        }
        .stat-box .label { font-size: 13px; opacity: 0.9; margin-bottom: 5px; }
        .stat-box .value { font-size: 28px; font-weight: 700; }
        
        .tabs {
            display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid #e2e8f0;
        }
        .tab {
            padding: 12px 24px; cursor: pointer; border: none; background: none;
            font-weight: 600; color: #64748b; transition: all 0.3s; border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            color: #4299e1; border-bottom-color: #4299e1;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;
        }
        .panel h2 { font-size: 20px; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f7fafc; padding: 15px; text-align: left; font-weight: 600;
            font-size: 13px; color: #4a5568; text-transform: uppercase;
        }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .progress-bar {
            background: #e2e8f0; height: 8px; border-radius: 10px; overflow: hidden; width: 100px;
        }
        .progress-fill {
            height: 100%; background: linear-gradient(90deg, #4299e1, #667eea);
        }
        
        .material-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;
        }
        
        .material-card {
            background: white; border: 2px solid #e2e8f0; border-radius: 12px;
            padding: 20px; transition: all 0.3s;
        }
        .material-card:hover {
            border-color: #4299e1; box-shadow: 0 5px 15px rgba(66, 153, 225, 0.2);
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_instructor.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <div class="header-top">
                    <div>
                        <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                        <h1><?php echo htmlspecialchars($course['course_name']); ?></h1>
                        <p style="color: #64748b; font-size: 14px; margin-top: 5px;">
                            📚 <?php echo htmlspecialchars($course['category']); ?> · 
                            ⏱️ <?php echo htmlspecialchars($course['duration']); ?>
                        </p>
                    </div>
                    <a href="my_courses.php" class="btn btn-secondary">← Back</a>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="label">Total Students</div>
                        <div class="value"><?php echo $course['total_students']; ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Average Progress</div>
                        <div class="value"><?php echo number_format($course['avg_progress'] ?? 0, 0); ?>%</div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Materials</div>
                        <div class="value"><?php echo count($materials); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Schedules</div>
                        <div class="value"><?php echo count($schedules); ?></div>
                    </div>
                </div>
                
                <p style="color: #4a5568; line-height: 1.6;">
                    <?php echo htmlspecialchars($course['description']); ?>
                </p>
            </div>
            
            <div class="tabs">
                <button class="tab active" onclick="switchTab('students')">👥 Students</button>
                <button class="tab" onclick="switchTab('materials')">📄 Materials</button>
                <button class="tab" onclick="switchTab('schedules')">📅 Schedules</button>
            </div>
            
            <!-- Students Tab -->
            <div id="students-tab" class="tab-content active">
                <div class="panel">
                    <h2>📋 Enrolled Students</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Email</th>
                                <th>Progress</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Enrolled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $student['progress']; ?>%"></div>
                                        </div>
                                        <small><?php echo $student['progress']; ?>%</small>
                                    </td>
                                    <td><?php echo $student['final_grade'] ? number_format($student['final_grade'], 1) : '-'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $student['status'] == 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($student['enrolled_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Materials Tab -->
            <div id="materials-tab" class="tab-content">
                <div class="panel">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <h2>📄 Course Materials</h2>
                        <a href="materials.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">+ Add Material</a>
                    </div>
                    <div class="material-grid">
                        <?php foreach ($materials as $material): ?>
                            <div class="material-card">
                                <h4 style="margin-bottom: 8px;"><?php echo htmlspecialchars($material['title']); ?></h4>
                                <p style="color: #64748b; font-size: 13px; margin-bottom: 12px;">
                                    <?php echo htmlspecialchars(substr($material['description'], 0, 100)); ?>...
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="badge badge-info"><?php echo ucfirst($material['material_type']); ?></span>
                                    <?php if ($material['file_url']): ?>
                                        <a href="<?php echo htmlspecialchars($material['file_url']); ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                            Open →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Schedules Tab -->
            <div id="schedules-tab" class="tab-content">
                <div class="panel">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <h2>📅 Class Schedules</h2>
                        <a href="schedules.php" class="btn btn-primary">+ Add Schedule</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Topic</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($schedule['schedule_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['room']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['topic']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $schedule['status'] == 'scheduled' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($schedule['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>