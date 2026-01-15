<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$success = '';
$error = '';

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = 'Enrollment berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus enrollment!';
    }
}

// Handle ADD/EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $student_id = (int)$_POST['student_id'];
    $course_id = (int)$_POST['course_id'];
    $status = $_POST['status'];
    $progress = (int)$_POST['progress'];
    
    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE enrollments SET student_id=?, course_id=?, status=?, progress=? WHERE id=?");
        $stmt->bind_param("iisii", $student_id, $course_id, $status, $progress, $id);
        
        if ($stmt->execute()) {
            $success = 'Enrollment berhasil diupdate!';
        } else {
            $error = 'Gagal update enrollment!';
        }
    } else {
        // Check if already enrolled
        $check = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=?");
        $check->bind_param("ii", $student_id, $course_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Student sudah terdaftar di kursus ini!';
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status, progress) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $student_id, $course_id, $status, $progress);
            
            if ($stmt->execute()) {
                $success = 'Enrollment berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambah enrollment!';
            }
        }
    }
}

// Get all enrollments
$enrollments = $conn->query("
    SELECT e.*, 
           u.full_name as student_name, u.username as student_username,
           c.course_name, c.course_code
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY e.created_at DESC
");

// Get students for dropdown
$students = $conn->query("SELECT id, full_name, username FROM users WHERE role='student' AND status='active' ORDER BY full_name");

// Get courses for dropdown
$courses = $conn->query("SELECT id, course_name, course_code FROM courses WHERE status='active' ORDER BY course_name");

// Get enrollment for edit
$edit_enrollment = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM enrollments WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_enrollment = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollments Management - Skynusa Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            padding: 30px 0; overflow-y: auto; z-index: 1000;
        }
        .logo { padding: 0 30px 30px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo h2 { color: white; font-size: 24px; font-weight: 700; }
        .logo p { color: #94a3b8; font-size: 12px; margin-top: 5px; }
        
        .nav-menu { padding: 20px 0; }
        .nav-item {
            display: flex; align-items: center; padding: 12px 30px; color: #cbd5e1;
            text-decoration: none; transition: all 0.3s;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #3b82f6;
        }
        .nav-item i { margin-right: 15px; width: 20px; text-align: center; }
        
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
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4); }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-danger { background: #ef4444; color: white; }
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
        
        .form-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 20px; margin-bottom: 20px;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group label {
            font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #475569;
        }
        .form-group input, .form-group select {
            padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: #3b82f6;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc; padding: 15px; text-align: left; font-weight: 600;
            font-size: 13px; color: #475569; text-transform: uppercase;
        }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px;
            font-weight: 600; display: inline-block;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .progress-bar {
            background: #e5e7eb; height: 8px; border-radius: 10px;
            overflow: hidden; width: 100px;
        }
        .progress-fill {
            height: 100%; background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        }
        
        .action-buttons { display: flex; gap: 8px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar .logo h2, .sidebar .logo p, .nav-item span { display: none; }
            .main-content { margin-left: 70px; }
            .form-grid { grid-template-columns: 1fr; }
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
            <a href="dashboard.php" class="nav-item">
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
            <a href="enrollments.php" class="nav-item active">
                <i class="fas fa-clipboard-list"></i><span>Enrollments</span>
            </a>
            <a href="../logout.php" class="nav-item" style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>📝 Enrollments Management</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="panel">
            <h2 style="margin-bottom: 20px;"><?php echo $edit_enrollment ? '✏️ Edit Enrollment' : '➕ Add New Enrollment'; ?></h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit_enrollment ? $edit_enrollment['id'] : '0'; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student *</label>
                        <select name="student_id" required>
                            <option value="">Select Student</option>
                            <?php 
                            mysqli_data_seek($students, 0);
                            while ($student = $students->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $student['id']; ?>" 
                                    <?php echo ($edit_enrollment && $edit_enrollment['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name']) . ' (' . $student['username'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Course *</label>
                        <select name="course_id" required>
                            <option value="">Select Course</option>
                            <?php 
                            mysqli_data_seek($courses, 0);
                            while ($course = $courses->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $course['id']; ?>" 
                                    <?php echo ($edit_enrollment && $edit_enrollment['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code']) . ' - ' . htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="active" <?php echo ($edit_enrollment && $edit_enrollment['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo ($edit_enrollment && $edit_enrollment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="dropped" <?php echo ($edit_enrollment && $edit_enrollment['status'] == 'dropped') ? 'selected' : ''; ?>>Dropped</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Progress (0-100%)</label>
                        <input type="number" name="progress" min="0" max="100" 
                               value="<?php echo $edit_enrollment ? $edit_enrollment['progress'] : '0'; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_enrollment ? '💾 Update Enrollment' : '➕ Add Enrollment'; ?>
                </button>
                <?php if ($edit_enrollment): ?>
                    <a href="enrollments.php" class="btn btn-secondary">✖️ Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="panel">
            <h2 style="margin-bottom: 20px;">📋 Enrollments List</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Enrolled Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($enrollments->num_rows > 0): ?>
                            <?php while ($enrollment = $enrollments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enrollment['student_name']); ?></strong><br>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($enrollment['student_username']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enrollment['course_code']); ?></strong><br>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($enrollment['course_name']); ?></small>
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $enrollment['progress']; ?>%"></div>
                                        </div>
                                        <small style="color: #64748b;"><?php echo $enrollment['progress']; ?>%</small>
                                    </td>
                                    <td>
                                        <?php if ($enrollment['status'] == 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif ($enrollment['status'] == 'completed'): ?>
                                            <span class="badge badge-info">Completed</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Dropped</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($enrollment['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $enrollment['id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                            <a href="?delete=<?php echo $enrollment['id']; ?>" class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    📭 No enrollments yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>