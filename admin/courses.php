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

// Get all instructors
$instructors = $conn->query("SELECT id, full_name FROM users WHERE role='instructor' AND status='active'");

// Get categories
$categories = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''");

// Get course for edit
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
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background: #f8fafc; 
            color: #1e293b;
        }
        
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
            display: flex; align-items: center; padding: 12px 30px;
            color: #cbd5e1; text-decoration: none; transition: all 0.3s;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1); color: white;
            border-left: 4px solid #3b82f6;
        }
        .nav-item i { margin-right: 15px; width: 20px; text-align: center; }
        
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
        
        .header {
            background: white; padding: 25px 30px; border-radius: 15px;
            margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; font-weight: 700; }
        
        .toolbar {
            background: white; padding: 20px 25px; border-radius: 15px;
            margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .toolbar-row {
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }
        
        .search-box {
            position: relative; flex: 1; min-width: 250px;
        }
        
        .search-input {
            width: 100%; padding: 10px 15px 10px 40px;
            border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none; border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .search-icon {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); color: #94a3b8;
        }
        
        .filter-select {
            padding: 10px 15px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; min-width: 150px;
            cursor: pointer; transition: all 0.3s;
        }
        
        .filter-select:focus {
            outline: none; border-color: #3b82f6;
        }
        
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 25px;
        }
        
        .stat-card {
            background: white; padding: 20px; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #3b82f6;
        }
        
        .stat-label { color: #64748b; font-size: 13px; margin-bottom: 5px; }
        .stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 20px; margin-bottom: 20px;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-weight: 600; margin-bottom: 8px;
            font-size: 14px; color: #475569;
        }
        .form-group input, .form-group select, .form-group textarea {
            padding: 12px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #3b82f6;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc; padding: 15px; text-align: left;
            font-weight: 600; font-size: 13px; color: #475569;
            text-transform: uppercase;
        }
        td {
            padding: 15px; border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        tr:hover { background: #f8fafc; }
        
        .action-buttons { display: flex; gap: 8px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar .logo h2, .sidebar .logo p, .nav-item span { display: none; }
            .main-content { margin-left: 70px; }
            .form-grid { grid-template-columns: 1fr; }
            .toolbar-row { flex-direction: column; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <div>
                <h1>📚 Course Management</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage all courses, instructors, and enrollments</p>
            </div>
            <div class="d-flex gap-sm">
                <button onclick="exportTableToCSV('coursesTable', 'courses.csv')" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export
                </button>
                <button onclick="printElement('coursesTable')" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <div class="alert-content"><?php echo $success; ?></div>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">❌</span>
                <div class="alert-content"><?php echo $error; ?></div>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Total Courses</div>
                <div class="stat-value"><?php echo $courses->num_rows; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
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
            <div class="stat-card" style="border-left-color: #f59e0b;">
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
            <div class="stat-card" style="border-left-color: #8b5cf6;">
                <div class="stat-label">Instructors</div>
                <div class="stat-value"><?php echo $instructors->num_rows; ?></div>
            </div>
        </div>
        
        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" class="toolbar-row">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        placeholder="Search courses by code or name..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        onchange="this.form.submit()"
                    >
                </div>
                
                <select name="category" class="filter-select" onchange="this.form.submit()">
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
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                
                <?php if ($search || $category_filter || $status_filter): ?>
                    <a href="courses_improved.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
                
                <button type="button" onclick="document.getElementById('addEditForm').scrollIntoView({behavior: 'smooth'})" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Course
                </button>
            </form>
        </div>
        
        <!-- Add/Edit Form -->
        <div class="panel" id="addEditForm">
            <h2 style="margin-bottom: 20px;"><?php echo $edit_course ? '✏️ Edit Course' : '➕ Add New Course'; ?></h2>
            <form method="POST" onsubmit="Loading.show()">
                <input type="hidden" name="id" value="<?php echo $edit_course ? $edit_course['id'] : '0'; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label-required">Course Code</label>
                        <input type="text" name="course_code" value="<?php echo $edit_course ? htmlspecialchars($edit_course['course_code']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label-required">Instructor</label>
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
                        <label class="form-label-required">Course Name</label>
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
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $edit_course ? 'Update' : 'Add'; ?> Course
                    </button>
                    <?php if ($edit_course): ?>
                        <a href="courses_improved.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Courses Table -->
        <div class="panel">
            <h2 style="margin-bottom: 20px;">📋 Courses List</h2>
            <div class="table-responsive">
                <table id="coursesTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Name</th>
                            <th>Instructor</th>
                            <th>Category</th>
                            <th>Enrolled</th>
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
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $course['id']; ?>" class="btn btn-sm btn-secondary" data-tooltip="Edit Course">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="deleteConfirm(<?php echo $course['id']; ?>)" class="btn btn-sm btn-danger" data-tooltip="Delete Course">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    📭 No courses found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/utils.js"></script>
    <script>
        // Initialize pagination
        if (document.querySelectorAll('#coursesTable tbody tr').length > 10) {
            Pagination.init('coursesTable', 10);
        }
        
        // Delete confirmation
        async function deleteConfirm(id) {
            const confirmed = await confirmDelete('Are you sure you want to delete this course? This action cannot be undone.');
            
            if (confirmed) {
                Loading.show();
                window.location.href = '?delete=' + id;
            }
        }
        
        // Show success toast if course was added/updated
        <?php if ($success): ?>
            Toast.success('<?php echo $success; ?>');
        <?php endif; ?>
        
        <?php if ($error): ?>
            Toast.error('<?php echo $error; ?>');
        <?php endif; ?>
        
        // Search with debounce
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', Search.debounce(function() {
                this.form.submit();
            }, 500));
        }
    </script>
</body>
</html>
