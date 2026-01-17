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
    $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE instructor_id = ?)");
    $stmt->bind_param("ii", $id, $instructor_id);
    if ($stmt->execute()) {
        $success = 'Jadwal berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus jadwal!';
    }
}

// Handle ADD/EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $course_id = (int)$_POST['course_id'];
    $schedule_date = $_POST['schedule_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $room = $_POST['room'];
    $topic = $_POST['topic'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE schedules SET course_id=?, schedule_date=?, start_time=?, end_time=?, room=?, topic=?, description=?, status=? WHERE id=?");
        $stmt->bind_param("isssssssi", $course_id, $schedule_date, $start_time, $end_time, $room, $topic, $description, $status, $id);
        if ($stmt->execute()) {
            $success = 'Jadwal berhasil diupdate!';
        } else {
            $error = 'Gagal update jadwal!';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO schedules (course_id, schedule_date, start_time, end_time, room, topic, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $course_id, $schedule_date, $start_time, $end_time, $room, $topic, $description, $status);
        if ($stmt->execute()) {
            $success = 'Jadwal berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambah jadwal!';
        }
    }
}

// Get all schedules
$schedules = $conn->query("
    SELECT s.*, c.course_name, c.course_code
    FROM schedules s
    JOIN courses c ON s.course_id = c.id
    WHERE c.instructor_id = '$instructor_id'
    ORDER BY s.schedule_date ASC, s.start_time ASC
");

// Get my courses for dropdown
$my_courses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE instructor_id='$instructor_id' AND status='active'");

// Get schedule for edit
$edit_schedule = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM schedules WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_schedule = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mengajar - Skynusa Academy</title>
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
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f7fafc; padding: 15px; text-align: left; font-weight: 600;
            font-size: 13px; color: #4a5568; text-transform: uppercase;
        }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block;
        }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fed7d7; color: #742a2a; }
        
        .action-buttons { display: flex; gap: 8px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
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
                <li><a href="schedules.php" class="active"><span>📅</span> <span>Jadwal</span></a></li>
                <li><a href="materials.php"><span>📄</span> <span>Materi</span></a></li>
                <li><a href="students.php"><span>👥</span> <span>Peserta</span></a></li>
                <li><a href="evaluations.php"><span>⭐</span> <span>Evaluasi</span></a></li>
                <li><a href="../logout.php"><span>🚪</span> <span>Logout</span></a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <header class="header">
                <h1>📅 Jadwal Mengajar</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="panel">
                <h2 style="margin-bottom: 20px;"><?php echo $edit_schedule ? '✏️ Edit Jadwal' : '➕ Tambah Jadwal Baru'; ?></h2>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $edit_schedule ? $edit_schedule['id'] : '0'; ?>">
                    
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
                                        <?php echo ($edit_schedule && $edit_schedule['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal *</label>
                            <input type="date" name="schedule_date" value="<?php echo $edit_schedule ? $edit_schedule['schedule_date'] : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="scheduled" <?php echo ($edit_schedule && $edit_schedule['status'] == 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="completed" <?php echo ($edit_schedule && $edit_schedule['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo ($edit_schedule && $edit_schedule['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Waktu Mulai *</label>
                            <input type="time" name="start_time" value="<?php echo $edit_schedule ? $edit_schedule['start_time'] : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Waktu Selesai *</label>
                            <input type="time" name="end_time" value="<?php echo $edit_schedule ? $edit_schedule['end_time'] : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Ruangan</label>
                            <input type="text" name="room" value="<?php echo $edit_schedule ? htmlspecialchars($edit_schedule['room']) : ''; ?>" placeholder="e.g., Lab A-101">
                        </div>
                        
                        <div class="form-group full">
                            <label>Topik</label>
                            <input type="text" name="topic" value="<?php echo $edit_schedule ? htmlspecialchars($edit_schedule['topic']) : ''; ?>" placeholder="Topik yang akan dibahas">
                        </div>
                        
                        <div class="form-group full">
                            <label>Deskripsi</label>
                            <textarea name="description" placeholder="Deskripsi lengkap jadwal"><?php echo $edit_schedule ? htmlspecialchars($edit_schedule['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_schedule ? '💾 Update Jadwal' : '➕ Tambah Jadwal'; ?>
                    </button>
                    <?php if ($edit_schedule): ?>
                        <a href="schedules.php" class="btn btn-secondary">✖️ Batal</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="panel">
                <h2 style="margin-bottom: 20px;">📋 Daftar Jadwal Mengajar</h2>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Ruangan</th>
                                <th>Topik</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($schedules->num_rows > 0): ?>
                                <?php while ($schedule = $schedules->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($schedule['course_code']); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($schedule['course_name']); ?></small>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($schedule['schedule_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($schedule['start_time'])) . ' - ' . date('H:i', strtotime($schedule['end_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['room']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['topic']); ?></td>
                                        <td>
                                            <?php if ($schedule['status'] == 'scheduled'): ?>
                                                <span class="badge badge-success">Scheduled</span>
                                            <?php elseif ($schedule['status'] == 'completed'): ?>
                                                <span class="badge badge-warning">Completed</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?php echo $schedule['id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                                <a href="?delete=<?php echo $schedule['id']; ?>" class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Yakin ingin menghapus jadwal ini?')">🗑️ Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        📭 Belum ada jadwal mengajar
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>