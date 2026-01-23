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

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM materials WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE instructor_id = ?)");
    $stmt->bind_param("ii", $id, $instructor_id);
    if ($stmt->execute()) {
        $success = 'Materi berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus materi!';
    }
}

// Handle ADD/EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $course_id = (int)$_POST['course_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $material_type = $_POST['material_type'];
    $file_url = $_POST['file_url'];
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE materials SET course_id=?, title=?, description=?, material_type=?, file_url=? WHERE id=?");
        $stmt->bind_param("issssi", $course_id, $title, $description, $material_type, $file_url, $id);
        if ($stmt->execute()) {
            $success = 'Materi berhasil diupdate!';
        } else {
            $error = 'Gagal update materi!';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO materials (course_id, title, description, material_type, file_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $course_id, $title, $description, $material_type, $file_url);
        if ($stmt->execute()) {
            $success = 'Materi berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambah materi!';
        }
    }
}

// Filter by course
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_sql = $course_filter > 0 ? " AND m.course_id = $course_filter" : "";

// Get all materials
$materials = $conn->query("
    SELECT m.*, c.course_name, c.course_code
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    WHERE c.instructor_id = '$instructor_id' $filter_sql
    ORDER BY m.created_at DESC
");

// Get my courses for dropdown
$my_courses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE instructor_id='$instructor_id' AND status='active'");

// Get material for edit
$edit_material = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM materials WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_material = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi Pembelajaran - Skynusa Academy</title>
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
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        .materials-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;
        }
        
        .material-card {
            background: white; border: 2px solid #e2e8f0; border-radius: 12px;
            padding: 20px; transition: all 0.3s; position: relative;
        }
        .material-card:hover {
            border-color: #4299e1; box-shadow: 0 5px 15px rgba(66, 153, 225, 0.2);
            transform: translateY(-3px);
        }
        
        .material-icon {
            width: 50px; height: 50px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 15px;
        }
        .material-icon.pdf { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .material-icon.video { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .material-icon.link { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .material-icon.document { background: linear-gradient(135deg, #10b981, #059669); }
        
        .material-card h3 { font-size: 16px; margin-bottom: 8px; color: #2d3748; }
        .material-card p { color: #718096; font-size: 13px; margin-bottom: 15px; line-height: 1.5; }
        .material-meta {
            font-size: 12px; color: #94a3af; margin-bottom: 15px;
            padding-top: 10px; border-top: 1px solid #e2e8f0;
        }
        
        .badge {
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-pdf { background: #fee2e2; color: #991b1b; }
        .badge-video { background: #ede9fe; color: #5b21b6; }
        .badge-link { background: #dbeafe; color: #1e40af; }
        .badge-document { background: #d1fae5; color: #065f46; }
        
        .action-buttons { display: flex; gap: 8px; margin-top: 15px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .materials-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_instructor.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>📄 Materi Pembelajaran</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="panel">
                <h2 style="margin-bottom: 20px;"><?php echo $edit_material ? '✏️ Edit Materi' : '➕ Tambah Materi Baru'; ?></h2>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $edit_material ? $edit_material['id'] : '0'; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Kursus *</label>
                            <select name="course_id" required>
                                <option value="">Pilih Kursus</option>
                                <?php 
                                mysqli_data_seek($my_courses, 0);
                                while ($course = $my_courses->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                        <?php echo ($edit_material && $edit_material['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Judul Materi *</label>
                            <input type="text" name="title" value="<?php echo $edit_material ? htmlspecialchars($edit_material['title']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Tipe Materi *</label>
                            <select name="material_type" required>
                                <option value="pdf" <?php echo ($edit_material && $edit_material['material_type'] == 'pdf') ? 'selected' : ''; ?>>PDF</option>
                                <option value="video" <?php echo ($edit_material && $edit_material['material_type'] == 'video') ? 'selected' : ''; ?>>Video</option>
                                <option value="document" <?php echo ($edit_material && $edit_material['material_type'] == 'document') ? 'selected' : ''; ?>>Document</option>
                                <option value="link" <?php echo ($edit_material && $edit_material['material_type'] == 'link') ? 'selected' : ''; ?>>Link</option>
                                <option value="other" <?php echo ($edit_material && $edit_material['material_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group full">
                            <label>Deskripsi</label>
                            <textarea name="description" placeholder="Deskripsi materi"><?php echo $edit_material ? htmlspecialchars($edit_material['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group full">
                            <label>URL/Link Materi</label>
                            <input type="text" name="file_url" value="<?php echo $edit_material ? htmlspecialchars($edit_material['file_url']) : ''; ?>" placeholder="https://...">
                            <small style="color: #718096; margin-top: 5px;">Link ke file atau resource online (Google Drive, YouTube, dll)</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_material ? '💾 Update Materi' : '➕ Tambah Materi'; ?>
                    </button>
                    <?php if ($edit_material): ?>
                        <a href="materials.php" class="btn btn-secondary">✖️ Batal</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>📚 Daftar Materi</h2>
                </div>
                
                <div class="filter-bar">
                    <label>Filter Kursus:</label>
                    <select onchange="window.location.href='materials.php?course_id='+this.value">
                        <option value="0">Semua Kursus</option>
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
                
                <?php if ($materials->num_rows > 0): ?>
                    <div class="materials-grid">
                        <?php while ($material = $materials->fetch_assoc()): ?>
                            <div class="material-card">
                                <div class="material-icon <?php echo $material['material_type']; ?>">
                                    <?php
                                    $icons = ['pdf' => '📄', 'video' => '🎥', 'link' => '🔗', 'document' => '📝', 'other' => '📋'];
                                    echo $icons[$material['material_type']] ?? '📋';
                                    ?>
                                </div>
                                <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($material['description'], 0, 100)); ?><?php echo strlen($material['description']) > 100 ? '...' : ''; ?></p>
                                <div class="material-meta">
                                    <strong><?php echo htmlspecialchars($material['course_code']); ?></strong> - <?php echo htmlspecialchars($material['course_name']); ?><br>
                                    <span style="font-size: 11px;">📅 <?php echo date('d M Y', strtotime($material['created_at'])); ?></span>
                                </div>
                                <span class="badge badge-<?php echo $material['material_type']; ?>"><?php echo ucfirst($material['material_type']); ?></span>
                                
                                <div class="action-buttons">
                                    <?php if ($material['file_url']): ?>
                                        <a href="<?php echo htmlspecialchars($material['file_url']); ?>" target="_blank" class="btn btn-secondary btn-sm">🔗 Open</a>
                                    <?php endif; ?>
                                    <a href="?edit=<?php echo $material['id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                    <a href="?delete=<?php echo $material['id']; ?>" class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Yakin ingin menghapus materi ini?')">🗑️</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                        <div style="font-size: 48px; margin-bottom: 15px;">📚</div>
                        <h3>Belum ada materi</h3>
                        <p>Tambahkan materi pembelajaran untuk kursus Anda</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>