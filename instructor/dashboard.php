<?php
session_start();
require_once '../config/database.php';

// Cek apakah sudah login dan role instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../index.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Ambil statistik
$my_courses = mysqli_num_rows(query("SELECT * FROM courses WHERE instructor_id='$instructor_id'"));
$total_students = mysqli_num_rows(query("
    SELECT DISTINCT e.student_id 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.instructor_id='$instructor_id' AND e.status='active'
"));
$total_materials = mysqli_num_rows(query("
    SELECT m.* 
    FROM materials m 
    JOIN courses c ON m.course_id = c.id 
    WHERE c.instructor_id='$instructor_id'
"));

// Ambil kursus yang diampu
$courses = fetch_all(query("
    SELECT c.*, COUNT(e.id) as total_students
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status='active'
    WHERE c.instructor_id='$instructor_id'
    GROUP BY c.id
    ORDER BY c.created_at DESC
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Instruktur - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Skynusa Academy</h2>
                <p>Instruktur Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="my_courses.php">📚 Kursus Saya</a></li>
                <li><a href="schedules.php">📅 Jadwal</a></li>
                <li><a href="materials.php">📄 Materi</a></li>
                <li><a href="students.php">👥 Peserta</a></li>
                <li><a href="evaluations.php">⭐ Evaluasi</a></li>
                <li><a href="../logout.php">🚪 Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <h1>Dashboard Instruktur</h1>
                <div class="user-info">
                    <span>Selamat datang, <strong><?php echo $_SESSION['full_name']; ?></strong></span>
                    <a href="../logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Statistics Cards -->
                <div class="cards">
                    <div class="card">
                        <div class="card-icon blue">📚</div>
                        <h3>Kursus Saya</h3>
                        <div class="number"><?php echo $my_courses; ?></div>
                    </div>
                    <div class="card">
                        <div class="card-icon green">👨‍🎓</div>
                        <h3>Total Peserta</h3>
                        <div class="number"><?php echo $total_students; ?></div>
                    </div>
                    <div class="card">
                        <div class="card-icon orange">📄</div>
                        <h3>Total Materi</h3>
                        <div class="number"><?php echo $total_materials; ?></div>
                    </div>
                </div>

                <!-- My Courses Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h2>Kursus yang Diampu</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kursus</th>
                                <th>Durasi</th>
                                <th>Jumlah Peserta</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">Belum ada kursus yang diampu</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo $course['course_name']; ?></td>
                                        <td><?php echo $course['duration']; ?></td>
                                        <td><?php echo $course['total_students']; ?> orang</td>
                                        <td>
                                            <?php if ($course['status'] == 'active'): ?>
                                                <span class="badge badge-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Tidak Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">Detail</a>
                                            <a href="materials.php?course_id=<?php echo $course['id']; ?>" class="btn btn-secondary btn-sm">Materi</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>