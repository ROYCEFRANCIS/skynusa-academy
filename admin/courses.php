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
        $success = 'Kursus berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus kursus!';
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
        // Update
        $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, description=?, instructor_id=?, category=?, duration=?, quota=?, start_date=?, end_date=?, status=? WHERE id=?");
        $stmt->bind_param("sssississi", $course_code, $course_name, $description, $instructor_id, $category, $duration, $quota, $start_date, $end_date, $status, $id);
        if ($stmt->execute()) {
            $success = 'Kursus berhasil diupdate!';
        } else {
            $error = 'Gagal update kursus!';
        }
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, instructor_id, category, duration, quota, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssississs", $course_code, $course_name, $description, $instructor_id, $category, $duration, $quota, $start_date, $end_date, $status);
        if ($stmt->execute()) {
            $success = 'Kursus berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambah kursus!';
        }
    }
}

// Get all courses
$courses = $conn->query("
    SELECT c.*, u.full_name as instructor_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status='active') as enrolled
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    ORDER BY c.created_at DESC
");

// Get all instructors for dropdown
$instructors = $conn->query("SELECT id, full_name FROM users WHERE role='instructor' AND status='active'");

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
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px;
            cursor: pointer; font-weight: 600; text-decoration: none;
            display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .alert {
            padding: 15px 20px; border-radius: 10px;
            margin-bottom: 20px; font-weight: 500;
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
        
        .table-responsive { overflow-x: auto; }
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
        
        .badge {
            padding: 5px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 600; display: inline-block;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        
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
            <a href="../logout.php" class="nav-item" style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>📚 Course Management</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="panel">
            <h2 style="margin-bottom: 20px;"><?php echo $edit_course ? '✏️ Edit Kursus' : '➕ Tambah Kursus Baru'; ?></h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit_course ? $edit_course['id'] : '0'; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Kode Kursus *</label>
                        <input type="text" name="course_code" value="<?php echo $edit_course ? htmlspecialchars($edit_course['course_code']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Instruktur *</label>
                        <select name="instructor_id" required>
                            <option value="">Pilih Instruktur</option>
                            <?php while ($instructor = $instructors->fetch_assoc()): ?>
                                <option value="<?php echo $instructor['id']; ?>" 
                                    <?php echo ($edit_course && $edit_course['instructor_id'] == $instructor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($instructor['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full">
                        <label>Nama Kursus *</label>
                        <input type="text" name="course_name" value="<?php echo $edit_course ? htmlspecialchars($edit_course['course_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group full">
                        <label>Deskripsi</label>
                        <textarea name="description"><?php echo $edit_course ? htmlspecialchars($edit_course['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Kategori</label>
                        <input type="text" name="category" value="<?php echo $edit_course ? htmlspecialchars($edit_course['category']) : ''; ?>" placeholder="e.g., Programming, Design">
                    </div>
                    
                    <div class="form-group">
                        <label>Durasi</label>
                        <input type="text" name="duration" value="<?php echo $edit_course ? htmlspecialchars($edit_course['duration']) : ''; ?>" placeholder="e.g., 12 Minggu">
                    </div>
                    
                    <div class="form-group">
                        <label>Kuota</label>
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
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" value="<?php echo $edit_course ? $edit_course['start_date'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Selesai</label>
                        <input type="date" name="end_date" value="<?php echo $edit_course ? $edit_course['end_date'] : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_course ? '💾 Update Kursus' : '➕ Tambah Kursus'; ?>
                </button>
                <?php if ($edit_course): ?>
                    <a href="courses.php" class="btn btn-secondary">✖️ Batal</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="panel">
            <h2 style="margin-bottom: 20px;">📋 Daftar Kursus</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Kursus</th>
                            <th>Instruktur</th>
                            <th>Kategori</th>
                            <th>Peserta</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($courses->num_rows > 0): ?>
                            <?php while ($course = $courses->fetch_assoc()): ?>
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
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $course['id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                            <a href="?delete=<?php echo $course['id']; ?>" class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Yakin ingin menghapus kursus ini?')">🗑️ Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    📭 Belum ada kursus. Silakan tambahkan kursus baru!
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