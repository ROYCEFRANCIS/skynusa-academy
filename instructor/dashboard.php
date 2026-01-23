<?php
session_start();
require_once '../config/database.php';

// Cek apakah sudah login dan role instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../index.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['full_name'];

// Ambil statistik real
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
$courses_query = "
    SELECT c.*, COUNT(DISTINCT e.id) as total_students
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status='active'
    WHERE c.instructor_id='$instructor_id'
    GROUP BY c.id
    ORDER BY c.created_at DESC
";
$courses = fetch_all(query($courses_query));

// Ambil jadwal mengajar terdekat
$upcoming_classes = fetch_all(query("
    SELECT s.*, c.course_name, c.course_code
    FROM schedules s
    JOIN courses c ON s.course_id = c.id
    WHERE c.instructor_id='$instructor_id' 
    AND s.schedule_date >= CURDATE()
    AND s.status = 'scheduled'
    ORDER BY s.schedule_date ASC, s.start_time ASC
    LIMIT 5
"));

// Format date
function formatDate($date) {
    if (empty($date)) return '-';
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    $timestamp = strtotime($date);
    $day = $days[date('w', $timestamp)];
    $date_num = date('d', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day, $date_num $month $year";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Instruktur - Skynusa Academy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f5f7fa;
            color: #2d3748;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #2c5282 0%, #2b6cb0 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 30px 25px;
            background: rgba(255,255,255,0.08);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
        }

        .instructor-profile {
            padding: 25px;
            background: rgba(255,255,255,0.05);
            margin: 20px;
            border-radius: 12px;
        }

        .instructor-profile .avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4299e1, #667eea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            border: 3px solid rgba(255,255,255,0.2);
        }

        .instructor-profile h3 {
            font-size: 17px;
            margin-bottom: 4px;
        }

        .instructor-profile p {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin: 3px 15px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 14px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            border-radius: 10px;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .sidebar-menu a span:first-child {
            margin-right: 12px;
            font-size: 18px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            color: #2d3748;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4299e1, #667eea);
            color: white;
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        /* Stats Cards */
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .card-icon.blue {
            background: linear-gradient(135deg, #4299e1, #3182ce);
        }

        .card-icon.green {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        .card-icon.orange {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
        }

        .card h3 {
            color: #718096;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .card .number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-header h2 {
            font-size: 20px;
            color: #2d3748;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #f7fafc;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-danger {
            background: #fed7d7;
            color: #742a2a;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar-header h2,
            .sidebar-header p,
            .instructor-profile h3,
            .instructor-profile p,
            .sidebar-menu span:last-child {
                display: none;
            }

            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
<?php include 'sidebar_instructor.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <h1>👨‍🏫 Dashboard Instruktur</h1>
                <div class="user-info">
                    <span>Selamat datang, <strong><?php echo $instructor_name; ?></strong></span>
                    <a href="../logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
            </header>

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
                    <h2>📚 Kursus yang Diampu</h2>
                    <a href="my_courses.php" class="btn btn-primary">Lihat Semua</a>
                </div>
                <?php if (empty($courses)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📚</div>
                        <h3>Belum ada kursus</h3>
                        <p>Anda belum mengampu kursus apapun</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Kursus</th>
                                <th>Nama Kursus</th>
                                <th>Durasi</th>
                                <th>Jumlah Peserta</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['duration']); ?></td>
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
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Upcoming Classes -->
            <div class="table-container">
                <div class="table-header">
                    <h2>📅 Jadwal Mengajar Terdekat</h2>
                    <a href="schedules.php" class="btn btn-primary">Lihat Semua</a>
                </div>
                <?php if (empty($upcoming_classes)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <p>Tidak ada jadwal mengajar yang akan datang</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Ruangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_classes as $class): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($class['course_name']); ?></strong></td>
                                    <td><?php echo formatDate($class['schedule_date']); ?></td>
                                    <td><?php echo date('H:i', strtotime($class['start_time'])) . ' - ' . date('H:i', strtotime($class['end_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($class['room']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>