<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Get student's schedule
$schedules = $conn->query("
    SELECT s.*, c.course_name, c.course_code, u.full_name as instructor_name
    FROM schedules s
    JOIN courses c ON s.course_id = c.id
    JOIN users u ON c.instructor_id = u.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = '$student_id'
    ORDER BY s.schedule_date ASC, s.start_time ASC
");

// Group schedules by date
$grouped_schedules = [];
while ($schedule = $schedules->fetch_assoc()) {
    $date = $schedule['schedule_date'];
    if (!isset($grouped_schedules[$date])) {
        $grouped_schedules[$date] = [];
    }
    $grouped_schedules[$date][] = $schedule;
}

function formatDateHeader($date) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    $timestamp = strtotime($date);
    $day = $days[date('w', $timestamp)];
    $date_num = date('d', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day, $date_num $month $year";
}

function isToday($date) {
    return date('Y-m-d') === $date;
}

function isPast($date) {
    return strtotime($date) < strtotime(date('Y-m-d'));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f0f2f5; color: #1a1a1a; }
        
        .dashboard { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px; background: linear-gradient(180deg, #1e3a8a 0%, #312e81 100%);
            color: white; padding: 30px 0; position: fixed; height: 100vh; overflow-y: auto;
        }
        .sidebar-header { padding: 0 30px 30px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.7); }
        
        .user-profile { padding: 25px 30px; background: rgba(255,255,255,0.05); margin: 20px 0; }
        .user-profile .avatar {
            width: 50px; height: 50px; background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: 700; margin-bottom: 10px;
        }
        
        .sidebar-menu { list-style: none; padding: 20px 0; }
        .sidebar-menu li { margin: 5px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 14px 30px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; font-size: 15px;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 35px; }
        .sidebar-menu a.active { background: rgba(255,255,255,0.15); color: white; border-left: 4px solid #60a5fa; }
        .sidebar-menu a span { margin-right: 12px; font-size: 18px; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; }
        
        .header {
            background: white; padding: 25px 30px; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; color: #1a1a1a; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-secondary { background: #e5e7eb; color: #4b5563; }
        
        .date-group {
            margin-bottom: 30px;
        }
        
        .date-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 15px 25px; border-radius: 12px 12px 0 0;
            font-weight: 700; font-size: 18px; display: flex;
            justify-content: space-between; align-items: center;
        }
        .date-header.today { background: linear-gradient(135deg, #10b981, #059669); }
        .date-header.past { background: linear-gradient(135deg, #6b7280, #4b5563); }
        
        .schedule-list {
            background: white; border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .schedule-item {
            padding: 20px 25px; border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s;
        }
        .schedule-item:last-child { border-bottom: none; }
        .schedule-item:hover { background: #f9fafb; }
        
        .schedule-time {
            display: flex; align-items: center; gap: 10px;
            font-weight: 700; font-size: 16px; color: #1a1a1a; margin-bottom: 10px;
        }
        
        .schedule-course {
            font-size: 18px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px;
        }
        
        .schedule-details {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px; margin-top: 12px; padding: 15px; background: #f9fafb;
            border-radius: 8px;
        }
        
        .schedule-detail-item {
            display: flex; align-items: center; gap: 8px;
            font-size: 14px; color: #6b7280;
        }
        
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-scheduled { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        
        .empty-state {
            text-align: center; padding: 80px 20px; background: white;
            border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>📅 My Class Schedule</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if (!empty($grouped_schedules)): ?>
                <?php foreach ($grouped_schedules as $date => $date_schedules): ?>
                    <div class="date-group">
                        <div class="date-header <?php echo isToday($date) ? 'today' : (isPast($date) ? 'past' : ''); ?>">
                            <span><?php echo formatDateHeader($date); ?></span>
                            <?php if (isToday($date)): ?>
                                <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 12px; font-size: 14px;">
                                    📍 Today
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="schedule-list">
                            <?php foreach ($date_schedules as $schedule): ?>
                                <div class="schedule-item">
                                    <div class="schedule-time">
                                        <span>🕐</span>
                                        <span><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></span>
                                        <span class="badge badge-<?php echo $schedule['status']; ?>">
                                            <?php echo ucfirst($schedule['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="schedule-course">
                                        <?php echo htmlspecialchars($schedule['course_code']); ?> - 
                                        <?php echo htmlspecialchars($schedule['course_name']); ?>
                                    </div>
                                    
                                    <?php if ($schedule['topic']): ?>
                                        <div style="color: #6b7280; font-size: 14px; margin-bottom: 12px;">
                                            📖 Topic: <?php echo htmlspecialchars($schedule['topic']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="schedule-details">
                                        <div class="schedule-detail-item">
                                            <span>👨‍🏫</span>
                                            <span><?php echo htmlspecialchars($schedule['instructor_name']); ?></span>
                                        </div>
                                        <?php if ($schedule['room']): ?>
                                            <div class="schedule-detail-item">
                                                <span>🚪</span>
                                                <span>Room: <?php echo htmlspecialchars($schedule['room']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($schedule['description']): ?>
                                            <div class="schedule-detail-item" style="grid-column: 1 / -1;">
                                                <span>📝</span>
                                                <span><?php echo htmlspecialchars($schedule['description']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h2>No Scheduled Classes</h2>
                    <p>You don't have any upcoming classes scheduled</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>