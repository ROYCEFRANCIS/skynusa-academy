<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details with enrollment
$course_query = "
    SELECT c.*, e.progress, e.status as enrollment_status, e.final_grade,
           u.full_name as instructor_name, u.email as instructor_email
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    JOIN users u ON c.instructor_id = u.id
    WHERE c.id = '$course_id' AND e.student_id = '$student_id'
";
$course_result = query($course_query);
$course = fetch_one($course_result);

if (!$course) {
    header("Location: my_courses.php");
    exit();
}

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
    AND schedule_date >= CURDATE()
    ORDER BY schedule_date ASC, start_time ASC
    LIMIT 5
"));

// Get assignments - FIXED: Changed 'grade' to 'score'
$assignments = fetch_all(query("
    SELECT a.*, 
           s.score as my_score,
           s.submitted_at,
           s.status as submission_status
    FROM assignments a
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = '$student_id'
    WHERE a.course_id = '$course_id'
    ORDER BY a.due_date ASC
"));

// Get other enrolled students count
$classmates = mysqli_num_rows(query("
    SELECT * FROM enrollments 
    WHERE course_id = '$course_id' AND status = 'active' AND student_id != '$student_id'
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_name']); ?> - Skynusa Academy</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .header-top { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; }
        .header h1 { font-size: 28px; color: #1a1a1a; margin-bottom: 8px; }
        .header .course-code {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .header .meta { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #e5e7eb; color: #4b5563; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .progress-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px;
        }
        .progress-label { display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: 600; }
        .progress-bar {
            background: rgba(255,255,255,0.3); height: 12px; border-radius: 10px; overflow: hidden;
        }
        .progress-fill { height: 100%; background: white; transition: width 0.3s; }
        
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px; margin-top: 15px;
        }
        .stat-box {
            background: rgba(255,255,255,0.15); padding: 15px; border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        .stat-box .label { font-size: 13px; opacity: 0.9; margin-bottom: 5px; }
        .stat-box .value { font-size: 24px; font-weight: 700; }
        
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;
        }
        .panel h2 { font-size: 20px; margin-bottom: 20px; color: #1a1a1a; }
        
        .material-item, .schedule-item, .assignment-item {
            padding: 15px; border-bottom: 1px solid #e5e7eb;
            display: flex; justify-content: space-between; align-items: center;
        }
        .material-item:last-child, .schedule-item:last-child, .assignment-item:last-child {
            border-bottom: none;
        }
        .material-item:hover, .schedule-item:hover, .assignment-item:hover {
            background: #f9fafb; border-radius: 8px;
        }
        
        .item-icon {
            width: 40px; height: 40px; border-radius: 8px; display: flex;
            align-items: center; justify-content: center; font-size: 18px; margin-right: 15px;
        }
        .item-content { flex: 1; }
        .item-content h4 { font-size: 15px; margin-bottom: 4px; color: #1a1a1a; }
        .item-content p { font-size: 13px; color: #6b7280; }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .empty-state {
            text-align: center; padding: 40px 20px; color: #9ca3af;
        }
        
        @media (max-width: 968px) {
            .content-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <div class="header-top">
                    <div>
                        <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                        <h1><?php echo htmlspecialchars($course['course_name']); ?></h1>
                        <p class="meta">
                            👨‍🏫 <?php echo htmlspecialchars($course['instructor_name']); ?> · 
                            📚 <?php echo htmlspecialchars($course['category']); ?> · 
                            👥 <?php echo $classmates + 1; ?> students
                        </p>
                    </div>
                    <a href="my_courses.php" class="btn btn-secondary">← Back to My Courses</a>
                </div>
                
                <div class="progress-section">
                    <div class="progress-label">
                        <span>Course Progress</span>
                        <span><?php echo number_format($course['progress'], 0); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="label">Duration</div>
                            <div class="value"><?php echo htmlspecialchars($course['duration']); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="label">Status</div>
                            <div class="value"><?php echo ucfirst($course['enrollment_status']); ?></div>
                        </div>
                        <?php if ($course['final_grade']): ?>
                        <div class="stat-box">
                            <div class="label">Final Grade</div>
                            <div class="value"><?php echo number_format($course['final_grade'], 1); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <p style="color: #4b5563; line-height: 1.6;">
                    <?php echo htmlspecialchars($course['description']); ?>
                </p>
            </div>
            
            <div class="content-grid">
                <div>
                    <!-- Materials -->
                    <div class="panel">
                        <h2>📄 Course Materials</h2>
                        <?php if (empty($materials)): ?>
                            <div class="empty-state">No materials available yet</div>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <div class="material-item">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <div class="item-icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                                            <?php
                                            $icons = ['pdf' => '📄', 'video' => '🎥', 'link' => '🔗', 'document' => '📝'];
                                            echo $icons[$material['material_type']] ?? '📋';
                                            ?>
                                        </div>
                                        <div class="item-content">
                                            <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                                            <p><?php echo htmlspecialchars(substr($material['description'], 0, 60)); ?>...</p>
                                        </div>
                                    </div>
                                    <?php if ($material['file_url']): ?>
                                        <a href="<?php echo htmlspecialchars($material['file_url']); ?>" target="_blank" class="btn btn-primary btn-sm">
                                            Open →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Assignments -->
                    <div class="panel">
                        <h2>📝 Assignments</h2>
                        <?php if (empty($assignments)): ?>
                            <div class="empty-state">No assignments yet</div>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <div class="assignment-item">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <div class="item-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                                            📝
                                        </div>
                                        <div class="item-content">
                                            <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                            <p>Due: <?php echo date('d M Y', strtotime($assignment['due_date'])); ?>
                                            <?php if ($assignment['my_score']): ?>
                                                · Score: <?php echo number_format($assignment['my_score'], 1); ?>/<?php echo $assignment['max_score']; ?>
                                            <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($assignment['submitted_at']): ?>
                                        <?php if ($assignment['submission_status'] == 'graded'): ?>
                                            <span class="badge badge-success">Graded</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Submitted</span>
                                        <?php endif; ?>
                                    <?php elseif (strtotime($assignment['due_date']) < time()): ?>
                                        <span class="badge badge-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <!-- Upcoming Schedule -->
                    <div class="panel">
                        <h2>📅 Upcoming Classes</h2>
                        <?php if (empty($schedules)): ?>
                            <div class="empty-state">No upcoming classes</div>
                        <?php else: ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <div class="schedule-item">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <div class="item-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                                            📅
                                        </div>
                                        <div class="item-content">
                                            <h4><?php echo htmlspecialchars($schedule['topic'] ?: 'Class Session'); ?></h4>
                                            <p>
                                                <?php echo date('d M Y', strtotime($schedule['schedule_date'])); ?><br>
                                                <?php echo date('H:i', strtotime($schedule['start_time'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['end_time'])); ?><br>
                                                📍 <?php echo htmlspecialchars($schedule['room']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Instructor Info -->
                    <div class="panel">
                        <h2>👨‍🏫 Instructor</h2>
                        <div style="padding: 15px; background: #f9fafb; border-radius: 8px;">
                            <h3 style="font-size: 16px; margin-bottom: 8px;">
                                <?php echo htmlspecialchars($course['instructor_name']); ?>
                            </h3>
                            <p style="font-size: 14px; color: #6b7280;">
                                📧 <?php echo htmlspecialchars($course['instructor_email']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>