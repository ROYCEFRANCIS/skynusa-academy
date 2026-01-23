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
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = 'Student berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus student!';
    }
}

// Handle ADD/EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = $_POST['username'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $status = $_POST['status'];
    
    if ($id > 0) {
        // Update
        if (!empty($_POST['password'])) {
            $password = md5($_POST['password']);
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, phone=?, address=?, status=?, password=? WHERE id=? AND role='student'");
            $stmt->bind_param("sssssssi", $username, $full_name, $email, $phone, $address, $status, $password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, phone=?, address=?, status=? WHERE id=? AND role='student'");
            $stmt->bind_param("ssssssi", $username, $full_name, $email, $phone, $address, $status, $id);
        }
        
        if ($stmt->execute()) {
            $success = 'Student berhasil diupdate!';
        } else {
            $error = 'Gagal update student!';
        }
    } else {
        // Insert
        $password = md5($_POST['password']);
        $role = 'student';
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $password, $full_name, $email, $phone, $address, $role, $status);
        
        if ($stmt->execute()) {
            $success = 'Student berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambah student!';
        }
    }
}

// Get all students
$students = $conn->query("
    SELECT u.*, 
           COUNT(DISTINCT e.id) as total_courses,
           COUNT(DISTINCT CASE WHEN e.status='completed' THEN e.id END) as completed_courses
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.student_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

// Get student for edit
$edit_student = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_student = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
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
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #475569;
        }
        .form-group input, .form-group select, .form-group textarea {
            padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #3b82f6;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc; padding: 15px; text-align: left; font-weight: 600;
            font-size: 13px; color: #475569; text-transform: uppercase;
        }
        td {
            padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px;
        }
        tr:hover { background: #f8fafc; }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px;
            font-weight: 600; display: inline-block;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
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
<?php include 'sidebar_admin.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>👨‍🎓 Students Management</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="panel">
            <h2 style="margin-bottom: 20px;"><?php echo $edit_student ? '✏️ Edit Student' : '➕ Add New Student'; ?></h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit_student ? $edit_student['id'] : '0'; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" value="<?php echo $edit_student ? htmlspecialchars($edit_student['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password <?php echo $edit_student ? '(kosongkan jika tidak diubah)' : '*'; ?></label>
                        <input type="password" name="password" <?php echo $edit_student ? '' : 'required'; ?>>
                    </div>
                    
                    <div class="form-group full">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo $edit_student ? htmlspecialchars($edit_student['full_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo $edit_student ? htmlspecialchars($edit_student['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo $edit_student ? htmlspecialchars($edit_student['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group full">
                        <label>Address</label>
                        <textarea name="address"><?php echo $edit_student ? htmlspecialchars($edit_student['address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo ($edit_student && $edit_student['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_student && $edit_student['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_student ? '💾 Update Student' : '➕ Add Student'; ?>
                </button>
                <?php if ($edit_student): ?>
                    <a href="students.php" class="btn btn-secondary">✖️ Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="panel">
            <h2 style="margin-bottom: 20px;">📋 Students List</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Courses</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students->num_rows > 0): ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                    <td><span class="badge badge-info"><?php echo $student['total_courses']; ?> courses</span></td>
                                    <td>
                                        <?php if ($student['status'] == 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                            <a href="?delete=<?php echo $student['id']; ?>" class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    📭 No students yet
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

