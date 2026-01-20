<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../index.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['full_name'];

// Get courses for dropdown
$my_courses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE instructor_id='$instructor_id'");

// Filter by course
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_sql = $course_filter > 0 ? " AND c.id = $course_filter" : "";

// Get students with their enrollments for evaluation
$students_query = "
    SELECT e.*, u.full_name as student_name, u.email,
           c.course_name, c.course_code
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE c.instructor_id = '$instructor_id' $filter_sql
    ORDER BY c.course_code, u.full_name
";
$students = fetch_all(query($students_query));

$success = '';
$error = '';

// Handle grade update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_grade'])) {
    $enrollment_id = (int)$_POST['enrollment_id'];
    $final_grade = (float)$_POST['final_grade'];
    $progress = (int)$_POST['progress'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE enrollments SET final_grade=?, progress=?, status=? WHERE id=?");
    $stmt->bind_param("disi", $final_grade, $progress, $status, $enrollment_id);
    
    if ($stmt->execute()) {
        $success = 'Grade updated successfully!';
        header("Location: evaluations.php" . ($course_filter ? "?course_id=$course_filter" : ""));
        exit();
    } else {
        $error = 'Failed to update grade!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Evaluations - Skynusa Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; font-weight: 700; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, #4299e1, #667eea); color: white; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .alert {
            padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;
        }
        
        .filter-bar {
            display: flex; gap: 15px; align-items: center; margin-bottom: 20px;
            padding: 15px; background: #f7fafc; border-radius: 10px;
        }
        .filter-bar select {
            padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; min-width: 250px;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f7fafc; padding: 15px; text-align: left; font-weight: 600;
            font-size: 13px; color: #4a5568; text-transform: uppercase;
        }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        
        .progress-bar {
            background: #e2e8f0; height: 8px; border-radius: 10px; overflow: hidden; width: 100px;
        }
        .progress-fill {
            height: 100%; background: linear-gradient(90deg, #4299e1, #667eea);
        }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white; margin: 5% auto; padding: 30px;
            border-radius: 15px; width: 90%; max-width: 500px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input, .form-group select {
            width: 100%; padding: 12px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>🎓 Skynusa Academy</h2>
                <p>Instruktur Panel</p>
            </div>
            <div class="instructor-profile">
                <div class="avatar"><?php echo strtoupper(substr($instructor_name, 0, 1)); ?></div>
                <h3><?php echo $instructor_name; ?></h3>
                <p>Instructor</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><span>📊</span> <span>Dashboard</span></a></li>
                <li><a href="my_courses.php"><span>📚</span> <span>Kursus Saya</span></a></li>
                <li><a href="schedules.php"><span>📅</span> <span>Jadwal</span></a></li>
                <li><a href="materials.php"><span>📄</span> <span>Materi</span></a></li>
                <li><a href="students.php"><span>👥</span> <span>Peserta</span></a></li>
                <li><a href="evaluations.php" class="active"><span>⭐</span> <span>Evaluasi</span></a></li>
                <li><a href="../logout.php"><span>🚪</span> <span>Logout</span></a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <header class="header">
                <h1>⭐ Student Evaluations</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="panel">
                <div class="filter-bar">
                    <label>Filter by Course:</label>
                    <select onchange="window.location.href='evaluations.php?course_id='+this.value">
                        <option value="0">All Courses</option>
                        <?php 
                        mysqli_data_seek($my_courses, 0);
                        while ($course = $my_courses->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <h2 style="margin-bottom: 20px;">📋 Student Performance</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Progress</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    📭 No students to evaluate
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['student_name']); ?></strong><br>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($student['email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['course_code']); ?></strong><br>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($student['course_name']); ?></small>
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $student['progress']; ?>%"></div>
                                        </div>
                                        <small><?php echo $student['progress']; ?>%</small>
                                    </td>
                                    <td>
                                        <?php echo $student['final_grade'] ? number_format($student['final_grade'], 1) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($student['status'] == 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif ($student['status'] == 'completed'): ?>
                                            <span class="badge badge-info">Completed</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Dropped</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="openGradeModal(<?php echo htmlspecialchars(json_encode($student)); ?>)" class="btn btn-primary btn-sm">
                                            ✏️ Grade
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Grade Modal -->
    <div id="gradeModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">✏️ Update Grade</h2>
            <form method="POST">
                <input type="hidden" name="enrollment_id" id="enrollment_id">
                
                <div class="form-group">
                    <label>Student</label>
                    <input type="text" id="student_name" readonly style="background: #f7fafc;">
                </div>
                
                <div class="form-group">
                    <label>Progress (%)</label>
                    <input type="number" name="progress" id="progress" min="0" max="100" required>
                </div>
                
                <div class="form-group">
                    <label>Final Grade (0-100)</label>
                    <input type="number" name="final_grade" id="final_grade" min="0" max="100" step="0.1">
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status">
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="dropped">Dropped</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeGradeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="update_grade" class="btn btn-primary">💾 Update Grade</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openGradeModal(student) {
            document.getElementById('enrollment_id').value = student.id;
            document.getElementById('student_name').value = student.student_name + ' - ' + student.course_code;
            document.getElementById('progress').value = student.progress;
            document.getElementById('final_grade').value = student.final_grade || '';
            document.getElementById('status').value = student.status;
            document.getElementById('gradeModal').style.display = 'block';
        }
        
        function closeGradeModal() {
            document.getElementById('gradeModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == document.getElementById('gradeModal')) {
                closeGradeModal();
            }
        }
    </script>
</body>
</html>