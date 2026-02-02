<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../index.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['full_name'];

$success = '';
$error = '';

// Handle DELETE assignment
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Only delete if assignment belongs to instructor's course
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE instructor_id = ?)");
    $stmt->bind_param("ii", $id, $instructor_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = 'Assignment berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus assignment!';
    }
}

// Handle ADD/EDIT assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_assignment'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $max_score = (float)$_POST['max_score'];
    $file_url = trim($_POST['file_url']);
    $status = $_POST['status'];
    
    // Verify course belongs to instructor
    $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $check->bind_param("ii", $course_id, $instructor_id);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $error = 'Invalid course selected!';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE assignments SET course_id=?, title=?, description=?, due_date=?, max_score=?, file_url=?, status=? WHERE id=? AND course_id IN (SELECT id FROM courses WHERE instructor_id = ?)");
            $stmt->bind_param("isssdssii", $course_id, $title, $description, $due_date, $max_score, $file_url, $status, $id, $instructor_id);
            if ($stmt->execute()) {
                $success = 'Assignment berhasil diupdate!';
            } else {
                $error = 'Gagal update assignment!';
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO assignments (course_id, title, description, due_date, max_score, file_url, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssdss", $course_id, $title, $description, $due_date, $max_score, $file_url, $status);
            if ($stmt->execute()) {
                $success = 'Assignment berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambah assignment!';
            }
        }
    }
}

// Handle GRADE submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $score = (float)$_POST['score'];
    $feedback = trim($_POST['feedback']);
    
    $stmt = $conn->prepare("UPDATE assignment_submissions SET score=?, feedback=?, status='graded', graded_at=NOW() WHERE id=?");
    $stmt->bind_param("dsi", $score, $feedback, $submission_id);
    if ($stmt->execute()) {
        $success = 'Submission berhasil dinilai!';
    } else {
        $error = 'Gagal menilai submission!';
    }
}

// Filter by course
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_sql = $course_filter > 0 ? " AND a.course_id = $course_filter" : "";

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'assignments';

// Get all assignments for instructor's courses
$assignments = $conn->query("
    SELECT a.*, c.course_name, c.course_code,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as total_submissions,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'graded') as graded_count,
           (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = a.course_id AND e.status = 'active') as total_students
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE c.instructor_id = '$instructor_id' $filter_sql
    ORDER BY a.due_date DESC
");

// Get submissions that need grading
$pending_submissions = $conn->query("
    SELECT s.*, a.title as assignment_title, a.max_score, a.course_id,
           c.course_name, c.course_code,
           u.full_name as student_name, u.email as student_email
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON s.student_id = u.id
    WHERE c.instructor_id = '$instructor_id'
    ORDER BY 
        CASE WHEN s.status = 'submitted' THEN 0 ELSE 1 END,
        s.submitted_at DESC
");

// Get my courses for dropdown
$my_courses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE instructor_id='$instructor_id' AND status='active'");

// Get assignment for edit
$edit_assignment = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("
        SELECT a.* FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        WHERE a.id = ? AND c.instructor_id = ?
    ");
    $stmt->bind_param("ii", $edit_id, $instructor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_assignment = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments Management - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
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
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4); }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .alert {
            padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        .tabs {
            display: flex; gap: 5px; margin-bottom: 25px; background: white;
            padding: 8px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .tab {
            padding: 12px 24px; cursor: pointer; border: none; background: none;
            font-weight: 600; color: #64748b; transition: all 0.3s; border-radius: 8px;
            font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .tab:hover { background: #f1f5f9; color: #334155; }
        .tab.active { background: linear-gradient(135deg, #4299e1, #667eea); color: white; }
        .tab .count {
            background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 10px;
            font-size: 12px;
        }
        .tab.active .count { background: rgba(255,255,255,0.3); }
        .tab:not(.active) .count { background: #e2e8f0; color: #64748b; }
        
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
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #475569; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #4299e1;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        
        .assignments-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px;
        }
        
        .assignment-card {
            background: white; border: 2px solid #e2e8f0; border-radius: 12px;
            padding: 20px; transition: all 0.3s; position: relative;
        }
        .assignment-card:hover {
            border-color: #4299e1; box-shadow: 0 5px 15px rgba(66, 153, 225, 0.2);
            transform: translateY(-3px);
        }
        .assignment-card.overdue { border-left: 4px solid #ef4444; }
        .assignment-card.due-soon { border-left: 4px solid #f59e0b; }
        .assignment-card.active-assignment { border-left: 4px solid #10b981; }
        
        .assignment-card h3 { font-size: 18px; margin-bottom: 8px; color: #2d3748; }
        .assignment-card .course-tag {
            display: inline-block; padding: 4px 10px; background: #dbeafe; color: #1e40af;
            border-radius: 12px; font-size: 12px; font-weight: 600; margin-bottom: 12px;
        }
        .assignment-card p { color: #718096; font-size: 13px; margin-bottom: 15px; line-height: 1.5; }
        
        .assignment-meta {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
            padding: 15px; background: #f7fafc; border-radius: 8px; margin-bottom: 15px;
        }
        .meta-item { display: flex; flex-direction: column; gap: 4px; }
        .meta-item .label { font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
        .meta-item .value { font-size: 14px; font-weight: 600; color: #2d3748; }
        
        .submission-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 15px; background: #f0fdf4; border-radius: 8px; margin-bottom: 15px;
        }
        .submission-bar .progress-text { font-size: 13px; font-weight: 600; color: #065f46; }
        .submission-progress {
            background: #d1fae5; height: 6px; border-radius: 10px; overflow: hidden; width: 100px;
        }
        .submission-progress-fill {
            height: 100%; background: linear-gradient(90deg, #10b981, #059669);
        }
        
        .badge {
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-submitted { background: #dbeafe; color: #1e40af; }
        .badge-graded { background: #d1fae5; color: #065f46; }
        .badge-overdue { background: #fee2e2; color: #991b1b; }
        
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        
        /* Submissions table */
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f7fafc; padding: 15px; text-align: left; font-weight: 600;
            font-size: 13px; color: #4a5568; text-transform: uppercase;
        }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        
        /* Grade Modal */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.5);
            align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white; padding: 30px; border-radius: 15px;
            width: 90%; max-width: 550px; max-height: 90vh; overflow-y: auto;
        }
        .modal-content h2 { margin-bottom: 20px; }
        
        .score-input-group {
            display: flex; align-items: center; gap: 10px;
        }
        .score-input-group input {
            flex: 1;
        }
        .score-input-group .max-score {
            font-size: 16px; font-weight: 700; color: #64748b; white-space: nowrap;
        }
        
        .empty-state {
            text-align: center; padding: 60px 20px; color: #94a3b8;
        }
        .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .assignments-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_instructor.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>📝 Assignments Management</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <?php
            $pending_count = 0;
            if ($pending_submissions) {
                $pending_submissions_copy = $conn->query("
                    SELECT COUNT(*) as cnt FROM assignment_submissions s
                    JOIN assignments a ON s.assignment_id = a.id
                    JOIN courses c ON a.course_id = c.id
                    WHERE c.instructor_id = '$instructor_id' AND s.status = 'submitted'
                ");
                $row = $pending_submissions_copy->fetch_assoc();
                $pending_count = $row['cnt'];
            }
            ?>
            <div class="tabs">
                <a href="?tab=assignments<?php echo $course_filter ? '&course_id='.$course_filter : ''; ?>" 
                   class="tab <?php echo $active_tab == 'assignments' ? 'active' : ''; ?>">
                    📋 Assignments
                </a>
                <a href="?tab=create" class="tab <?php echo $active_tab == 'create' || $edit_assignment ? 'active' : ''; ?>">
                    ➕ <?php echo $edit_assignment ? 'Edit' : 'Create'; ?> Assignment
                </a>
                <a href="?tab=submissions<?php echo $course_filter ? '&course_id='.$course_filter : ''; ?>" 
                   class="tab <?php echo $active_tab == 'submissions' ? 'active' : ''; ?>">
                    📤 Submissions
                    <?php if ($pending_count > 0): ?>
                        <span class="count"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <?php if ($active_tab == 'create' || $edit_assignment): ?>
            <!-- Create / Edit Assignment Form -->
            <div class="panel">
                <h2 style="margin-bottom: 20px;">
                    <?php echo $edit_assignment ? '✏️ Edit Assignment' : '➕ Create New Assignment'; ?>
                </h2>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $edit_assignment ? $edit_assignment['id'] : '0'; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Course *</label>
                            <select name="course_id" required>
                                <option value="">Select Course</option>
                                <?php 
                                mysqli_data_seek($my_courses, 0);
                                while ($course = $my_courses->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                        <?php echo ($edit_assignment && $edit_assignment['course_id'] == $course['id']) ? 'selected' : ''; ?>
                                        <?php echo ($course_filter && $course_filter == $course['id'] && !$edit_assignment) ? 'selected' : ''; ?>>
                                        <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group full">
                            <label>Assignment Title *</label>
                            <input type="text" name="title" value="<?php echo $edit_assignment ? htmlspecialchars($edit_assignment['title']) : ''; ?>" required placeholder="e.g., Tugas 1 - Dasar Pemrograman">
                        </div>
                        
                        <div class="form-group">
                            <label>Due Date *</label>
                            <input type="datetime-local" name="due_date" value="<?php echo $edit_assignment ? date('Y-m-d\TH:i', strtotime($edit_assignment['due_date'])) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Max Score *</label>
                            <input type="number" name="max_score" min="1" max="1000" step="0.1" value="<?php echo $edit_assignment ? $edit_assignment['max_score'] : '100'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?php echo ($edit_assignment && $edit_assignment['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($edit_assignment && $edit_assignment['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Attachment URL (optional)</label>
                            <input type="url" name="file_url" value="<?php echo $edit_assignment ? htmlspecialchars($edit_assignment['file_url']) : ''; ?>" placeholder="https://drive.google.com/...">
                        </div>
                        
                        <div class="form-group full">
                            <label>Description</label>
                            <textarea name="description" placeholder="Describe the assignment requirements, instructions, etc."><?php echo $edit_assignment ? htmlspecialchars($edit_assignment['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="save_assignment" class="btn btn-primary">
                            <?php echo $edit_assignment ? '💾 Update Assignment' : '➕ Create Assignment'; ?>
                        </button>
                        <?php if ($edit_assignment): ?>
                            <a href="assignments.php?tab=assignments" class="btn btn-secondary">✖️ Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php elseif ($active_tab == 'submissions'): ?>
            <!-- Submissions Tab -->
            <div class="panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>📤 Student Submissions</h2>
                </div>
                
                <div class="filter-bar">
                    <label>Filter by Course:</label>
                    <select onchange="window.location.href='assignments.php?tab=submissions&course_id='+this.value">
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
                
                <?php
                // Re-query with possible filter
                $sub_filter = $course_filter > 0 ? " AND a.course_id = $course_filter" : "";
                $filtered_submissions = $conn->query("
                    SELECT s.*, a.title as assignment_title, a.max_score, a.course_id,
                           c.course_name, c.course_code,
                           u.full_name as student_name, u.email as student_email
                    FROM assignment_submissions s
                    JOIN assignments a ON s.assignment_id = a.id
                    JOIN courses c ON a.course_id = c.id
                    JOIN users u ON s.student_id = u.id
                    WHERE c.instructor_id = '$instructor_id' $sub_filter
                    ORDER BY 
                        CASE WHEN s.status = 'submitted' THEN 0 ELSE 1 END,
                        s.submitted_at DESC
                ");
                ?>
                
                <?php if ($filtered_submissions && $filtered_submissions->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Assignment</th>
                                    <th>Course</th>
                                    <th>Submitted</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($sub = $filtered_submissions->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sub['student_name']); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($sub['student_email']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sub['assignment_title']); ?></strong>
                                        </td>
                                        <td>
                                            <span style="padding: 3px 8px; background: #dbeafe; color: #1e40af; border-radius: 8px; font-size: 12px; font-weight: 600;">
                                                <?php echo htmlspecialchars($sub['course_code']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y, H:i', strtotime($sub['submitted_at'])); ?></td>
                                        <td>
                                            <?php if ($sub['status'] == 'graded'): ?>
                                                <strong style="color: #10b981;"><?php echo number_format($sub['score'], 1); ?></strong>
                                                <span style="color: #94a3b8;">/ <?php echo $sub['max_score']; ?></span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['status'] == 'graded'): ?>
                                                <span class="badge badge-graded">✅ Graded</span>
                                            <?php else: ?>
                                                <span class="badge badge-submitted">📤 Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($sub['file_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($sub['file_url']); ?>" target="_blank" class="btn btn-secondary btn-sm">📎 View</a>
                                                <?php endif; ?>
                                                <button onclick="openGradeModal(<?php echo htmlspecialchars(json_encode($sub)); ?>)" class="btn btn-primary btn-sm">
                                                    ✏️ <?php echo $sub['status'] == 'graded' ? 'Re-grade' : 'Grade'; ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📤</div>
                        <h3>No Submissions Yet</h3>
                        <p>No students have submitted assignments yet</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- Assignments List Tab -->
            <div class="panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>📋 All Assignments</h2>
                    <a href="?tab=create" class="btn btn-primary">➕ New Assignment</a>
                </div>
                
                <div class="filter-bar">
                    <label>Filter by Course:</label>
                    <select onchange="window.location.href='assignments.php?tab=assignments&course_id='+this.value">
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
                
                <?php if ($assignments && $assignments->num_rows > 0): ?>
                    <div class="assignments-grid">
                        <?php while ($assignment = $assignments->fetch_assoc()): 
                            $is_overdue = strtotime($assignment['due_date']) < time();
                            $is_due_soon = !$is_overdue && (strtotime($assignment['due_date']) - time()) < (3 * 86400);
                            $sub_percentage = $assignment['total_students'] > 0 
                                ? round(($assignment['total_submissions'] / $assignment['total_students']) * 100) 
                                : 0;
                        ?>
                            <div class="assignment-card <?php echo $is_overdue ? 'overdue' : ($is_due_soon ? 'due-soon' : 'active-assignment'); ?>">
                                <span class="course-tag">
                                    <?php echo htmlspecialchars($assignment['course_code']); ?> - <?php echo htmlspecialchars($assignment['course_name']); ?>
                                </span>
                                <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($assignment['description'], 0, 120)); ?><?php echo strlen($assignment['description']) > 120 ? '...' : ''; ?></p>
                                
                                <div class="assignment-meta">
                                    <div class="meta-item">
                                        <span class="label">Due Date</span>
                                        <span class="value"><?php echo date('d M Y, H:i', strtotime($assignment['due_date'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="label">Max Score</span>
                                        <span class="value"><?php echo $assignment['max_score']; ?> pts</span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="label">Status</span>
                                        <span class="value">
                                            <span class="badge badge-<?php echo $assignment['status']; ?>">
                                                <?php echo ucfirst($assignment['status']); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="label">Deadline</span>
                                        <span class="value" style="color: <?php echo $is_overdue ? '#ef4444' : ($is_due_soon ? '#f59e0b' : '#10b981'); ?>;">
                                            <?php echo $is_overdue ? '⏰ Passed' : ($is_due_soon ? '⚠️ Soon' : '✅ Open'); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="submission-bar">
                                    <div>
                                        <span class="progress-text">
                                            📤 <?php echo $assignment['total_submissions']; ?> / <?php echo $assignment['total_students']; ?> submitted
                                        </span>
                                        <span style="font-size: 12px; color: #94a3b8; margin-left: 10px;">
                                            (<?php echo $assignment['graded_count']; ?> graded)
                                        </span>
                                    </div>
                                    <div class="submission-progress">
                                        <div class="submission-progress-fill" style="width: <?php echo $sub_percentage; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <a href="?tab=submissions&course_id=<?php echo $assignment['course_id']; ?>" class="btn btn-success btn-sm">📤 Submissions</a>
                                    <?php if ($assignment['file_url']): ?>
                                        <a href="<?php echo htmlspecialchars($assignment['file_url']); ?>" target="_blank" class="btn btn-secondary btn-sm">📎 File</a>
                                    <?php endif; ?>
                                    <a href="?edit=<?php echo $assignment['id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                    <a href="?delete=<?php echo $assignment['id']; ?>&tab=assignments<?php echo $course_filter ? '&course_id='.$course_filter : ''; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this assignment? All submissions will also be deleted.')">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <h3>No Assignments Yet</h3>
                        <p>Create your first assignment for your courses</p>
                        <br>
                        <a href="?tab=create" class="btn btn-primary">➕ Create Assignment</a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Grade Modal -->
    <div id="gradeModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">✏️ Grade Submission</h2>
            <form method="POST">
                <input type="hidden" name="submission_id" id="grade_submission_id">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Student</label>
                    <input type="text" id="grade_student_name" readonly style="background: #f7fafc;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Assignment</label>
                    <input type="text" id="grade_assignment_title" readonly style="background: #f7fafc;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Submitted File</label>
                    <div id="grade_file_link"></div>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Student Notes</label>
                    <div id="grade_student_notes" style="padding: 12px; background: #f7fafc; border-radius: 8px; font-size: 14px; min-height: 40px; color: #475569;"></div>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Score *</label>
                    <div class="score-input-group">
                        <input type="number" name="score" id="grade_score" min="0" step="0.1" required style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
                        <span class="max-score">/ <span id="grade_max_score">100</span></span>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Feedback</label>
                    <textarea name="feedback" id="grade_feedback" placeholder="Provide feedback for the student..." style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; min-height: 100px; width: 100%;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeGradeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="grade_submission" class="btn btn-success">💾 Save Grade</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openGradeModal(submission) {
            document.getElementById('grade_submission_id').value = submission.id;
            document.getElementById('grade_student_name').value = submission.student_name + ' (' + submission.student_email + ')';
            document.getElementById('grade_assignment_title').value = submission.assignment_title + ' - ' + submission.course_code;
            document.getElementById('grade_max_score').textContent = submission.max_score;
            document.getElementById('grade_score').max = submission.max_score;
            document.getElementById('grade_score').value = submission.score || '';
            document.getElementById('grade_feedback').value = submission.feedback || '';
            
            // File link
            var fileLinkDiv = document.getElementById('grade_file_link');
            if (submission.file_url) {
                fileLinkDiv.innerHTML = '<a href="' + submission.file_url + '" target="_blank" class="btn btn-secondary btn-sm">📎 View Submitted File</a>';
            } else {
                fileLinkDiv.innerHTML = '<span style="color: #94a3b8;">No file submitted</span>';
            }
            
            // Student notes
            var notesDiv = document.getElementById('grade_student_notes');
            notesDiv.textContent = submission.notes || 'No notes provided';
            
            document.getElementById('gradeModal').classList.add('active');
        }
        
        function closeGradeModal() {
            document.getElementById('gradeModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target == document.getElementById('gradeModal')) {
                closeGradeModal();
            }
        }
    </script>
</body>
</html>