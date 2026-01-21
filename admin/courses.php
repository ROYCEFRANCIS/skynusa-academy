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
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = 'Course deleted successfully!';
    } else {
        $error = 'Failed to delete course!';
    }
}

// Handle ADD/EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $description = $_POST['description'];
    $instructor_id = (int)$_POST['instructor_id'];
    $category = $_POST['category'];
    $duration = $_POST['duration'];
    $quota = (int)$_POST['quota'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, description=?, instructor_id=?, category=?, duration=?, quota=?, start_date=?, end_date=?, status=? WHERE id=?");
        $stmt->bind_param("sssississi", $course_code, $course_name, $description, $instructor_id, $category, $duration, $quota, $start_date, $end_date, $status, $id);
        if ($stmt->execute()) {
            $success = 'Course updated successfully!';
        } else {
            $error = 'Failed to update course!';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, instructor_id, category, duration, quota, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssississs", $course_code, $course_name, $description, $instructor_id, $category, $duration, $quota, $start_date, $end_date, $status);
        if ($stmt->execute()) {
            $success = 'Course added successfully!';
        } else {
            $error = 'Failed to add course!';
        }
    }
}

// Get all courses with search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = "1=1";
if ($search) {
    $search_safe = $conn->real_escape_string($search);
    $where .= " AND (c.course_code LIKE '%$search_safe%' OR c.course_name LIKE '%$search_safe%')";
}
if ($category_filter) {
    $category_safe = $conn->real_escape_string($category_filter);
    $where .= " AND c.category = '$category_safe'";
}
if ($status_filter) {
    $status_safe = $conn->real_escape_string($status_filter);
    $where .= " AND c.status = '$status_safe'";
}

$courses = $conn->query("
    SELECT c.*, u.full_name as instructor_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status='active') as enrolled
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    WHERE $where
    ORDER BY c.created_at DESC
");

$instructors = $conn->query("SELECT id, full_name FROM users WHERE role='instructor' AND status='active'");
$categories = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''");

$edit_course = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_course = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/enhanced-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 280px;
            background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            padding: 30px 0; overflow-y: auto; z-index: 1000;
        }
        .logo { padding: 0 30px 25px; border-bottom: 2px solid #f1f5f9; }
        .logo h2 {
            font-size: 26px; font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }
        .logo p { color: #64748b; font-size: 13px; font-weight: 500; }
        .nav-menu { padding: 20px 0; }
        .nav-item {
            display: flex; align-items: center; padding: 14px 30px;
            color: #475569; text-decoration: none; font-weight: 500;
            font-size: 15px; transition: all 0.3s ease; position: relative;
        }
        .nav-item::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 4px; background: linear-gradient(135deg, #667eea, #764ba2);
            transform: scaleY(0); transition: transform 0.3s ease;
        }
        .nav-item:hover, .nav-item.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.08), transparent);
            color: #667eea;
        }
        .nav-item.active::before { transform: scaleY(1); }
        .nav-item i { width: 24px; margin-right: 15px; font-size: 18px; }
        .main-content { margin-left: 280px; padding: 30px; min-height: 100vh; }
        .header {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px 30px; margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 5px; }
        .header p { color: #64748b; font-size: 15px; }
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-box {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 16px; padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
        }
        .stat-label { font-size: 13px; color: #64748b; font-weight: 500; margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 800; color: #1e293b; }
        .toolbar {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 16px; padding: 20px 25px; margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .toolbar-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .search-box { position: relative; flex: 1; min-width: 250px; }
        .search-box i {
            position: absolute; left: 15px; top: 50%;
            transform: translateY(-50%); color: #94a3b8;
        }
        .search-box input {
            width: 100%; padding: 12px 15px 12px 45px;
            border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 14px; transition: all 0.3s;
        }
        .search-box input:focus {
            outline: none; border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .filter-select {
            padding: 12px 15px; border: 2px solid #e2e8f0;
            border-radius: 12px; font-size: 14px; min-width: 150px;
            cursor: pointer; transition: all 0.3s;
        }
        .filter-select:focus { outline: none; border-color: #667eea; }
        .panel {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); margin-bottom: 30px;
        }
        .panel h2 { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 25px; }
        .form-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 20px; margin-bottom: 25px;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #475569; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 14px; font-family: inherit; transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #667eea; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        thead tr { background: #f8fafc; }
        th {
            padding: 15px; text-align: left; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        th:first-child { border-top-left-radius: 12px; }
        th:last-child { border-top-right-radius: 12px; }
        td { padding: 18px 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        tbody tr { transition: all 0.2s; }
        tbody tr:hover { background: #f8fafc; }
        .action-btns { display: flex; gap: 8px; }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar .logo h2, .sidebar .logo p, .nav-item span { display: none; }
            .main-content { margin-left: 70px; }
            .form-grid { grid-template-columns: 1fr; }
            .toolbar-row { flex-direction: column; }
            .search-box { width: 100%; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>🎓 SKYNUSA</h2>
            <p>Admin Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <a href="courses.php" class="nav-item active">
                <i class="fas fa-book"></i><span>Courses</span>
            </a>
            <a href="students.php" class="nav-item">
                <i class="fas fa-user-graduate"></i><span>Students</span>
            </a>
            <a href="instructors.php" class="nav-item">
                <i class="fas fa-chalkboard-teacher"></i><span>Instructors</span>
            </a>
            <a href="enrollments.php" class="nav-item">
                <i class="fas fa-clipboard-list"></i><span>Enrollments</span>
            </a>
            <a href="../logout.php" class="nav-item" style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <div>
                <h1>📚 Course Management</h1>
                <p>Manage all courses, instructors, and enrollments</p>
            </div>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        
        <div class="stats-row">
            <div class="stat-box" style="border-left-color: #667eea;">
                <div class="stat-label">Total Courses</div>
                <div class="stat-value"><?php echo $courses->num_rows; ?></div>
            </div>
            <div class="stat-box" style="border-left-color: #10b981;">
                <div class="stat-label">Active Courses</div>
                <div class="stat-value">
                    <?php 
                    mysqli_data_seek($courses, 0);
                    $active = 0;
                    while ($c = $courses->fetch_assoc()) {
                        if ($c['status'] == 'active') $active++;
                    }
                    echo $active;
                    mysqli_data_seek($courses, 0);
                    ?>
                </div>
            </div>
            <div class="stat-box" style="border-left-color: #f59e0b;">
                <div class="stat-label">Total Enrolled</div>
                <div class="stat-value">
                    <?php 
                    mysqli_data_seek($courses, 0);
                    $total_enrolled = 0;
                    while ($c = $courses->fetch_assoc()) {
                        $total_enrolled += $c['enrolled'];
                    }
                    echo $total_enrolled;
                    mysqli_data_seek($courses, 0);
                    ?>
                </div>
            </div>
            <div class="stat-box" style="border-left-color: #8b5cf6;">
                <div class="stat-label">Instructors</div>
                <div class="stat-value"><?php echo $instructors->num_rows; ?></div>
            </div>
        </div>
        
        <div class="toolbar">
            <form method="GET" class="toolbar-row">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search courses..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php 
                    mysqli_data_seek($categories, 0);
                    while ($cat = $categories->fetch_assoc()): 
                    ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                            <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($search || $category_filter || $status_filter): ?>
                    <a href="courses.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
                <button type="button" onclick="document.getElementById('addEditForm').scrollIntoView({behavior: 'smooth'})" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Course
                </button>
            </form>
        </div>
        
        <div class="panel" id="addEditForm">
            <h2><?php echo $edit_course ? '✏️ Edit Course' : '➕ Add New Course'; ?></h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit_course ? $edit_course['id'] : '0'; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course Code *</label>
                        <input type="text" name="course_code" value="<?php echo $edit_course ? htmlspecialchars($edit_course['course_code']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Instructor *</label>
                        <select name="instructor_id" required>
                            <option value="">Select Instructor</option>
                            <?php 
                            mysqli_data_seek($instructors, 0);
                            while ($instructor = $instructors->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $instructor['id']; ?>" 
                                    <?php echo ($edit_course && $edit_course['instructor_id'] == $instructor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($instructor['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Course Name *</label>
                        <input type="text" name="course_name" value="<?php echo $edit_course ? htmlspecialchars($edit_course['course_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description"><?php echo $edit_course ? htmlspecialchars($edit_course['description']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" value="<?php echo $edit_course ? htmlspecialchars($edit_course['category']) : ''; ?>" placeholder="e.g., Programming, Design">
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <input type="text" name="duration" value="<?php echo $edit_course ? htmlspecialchars($edit_course['duration']) : ''; ?>" placeholder="e.g., 12 Weeks">
                    </div>
                    <div class="form-group">
                        <label>Quota</label>
                        <input type="number" name="quota" value="<?php echo $edit_course ? $edit_course['quota'] : '30'; ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo ($edit_course && $edit_course['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_course && $edit_course['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $edit_course ? $edit_course['start_date'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $edit_course ? $edit_course['end_date'] : ''; ?>">
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $edit_course ? 'Update' : 'Add'; ?> Course
                    </button>
                    <?php if ($edit_course): ?>
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="panel">
            <h2>📋 All Courses</h2>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Course Name</th>
                        <th>Instructor</th>
                        <th>Category</th>
                        <th>Enrolled/Quota</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($courses->num_rows > 0): ?>
                        <?php 
                        mysqli_data_seek($courses, 0);
                        while ($course = $courses->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['instructor_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['category']); ?></td>
                                <td><?php echo $course['enrolled']; ?> / <?php echo $course['quota']; ?></td>
                                <td>
                                    <?php if ($course['status'] == 'active'): ?>
                                        <span class="badge badge-success badge-dot">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="?edit=<?php echo $course['id']; ?>" class="btn btn-sm btn-secondary" data-tooltip="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $course['id']; ?>)" class="btn btn-sm btn-danger" data-tooltip="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                📚 No courses found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="../assets/js/enhanced-ui.js"></script>
    <script>
        async function confirmDelete(id) {
            const result = await confirm({
                title: 'Delete Course',
                message: 'Are you sure you want to delete this course? This action cannot be undone.',
                confirmText: 'Yes, Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });
            if (result) {
                window.location.href = '?delete=' + id;
            }
        }
        <?php if ($success): ?>
            toast.success('<?php echo $success; ?>');
        <?php endif; ?>
        <?php if ($error): ?>
            toast.error('<?php echo $error; ?>');
        <?php endif; ?>
    </script>
</body>
</html>