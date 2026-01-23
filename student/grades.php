<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Get graded assignments
$graded_assignments = $conn->query("
    SELECT a.title, a.max_score, c.course_name, c.course_code,
           s.score, s.feedback, s.submitted_at, s.graded_at
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE s.student_id = '$student_id' AND s.status = 'graded'
    ORDER BY s.graded_at DESC
");

// Get course grades
$course_grades = $conn->query("
    SELECT c.*, e.final_grade, e.progress, e.status,
           u.full_name as instructor_name,
           AVG(s.score) as avg_assignment_score,
           COUNT(DISTINCT s.id) as total_graded
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.instructor_id = u.id
    LEFT JOIN assignments a ON c.id = a.course_id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = '$student_id' AND s.status = 'graded'
    WHERE e.student_id = '$student_id'
    GROUP BY c.id
    ORDER BY e.created_at DESC
");

// Calculate GPA (simple average)
$gpa_query = $conn->query("
    SELECT AVG(final_grade) as gpa
    FROM enrollments
    WHERE student_id = '$student_id' AND final_grade IS NOT NULL
");
$gpa_data = $gpa_query->fetch_assoc();
$gpa = $gpa_data['gpa'] ?? 0;

function getGradeColor($score) {
    if ($score >= 80) return '#10b981';
    if ($score >= 70) return '#3b82f6';
    if ($score >= 60) return '#f59e0b';
    return '#ef4444';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades - Skynusa Academy</title>
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
        
        .gpa-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 40px; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); text-align: center;
        }
        .gpa-label { font-size: 16px; margin-bottom: 10px; opacity: 0.9; }
        .gpa-value { font-size: 64px; font-weight: 700; }
        .gpa-scale { font-size: 14px; margin-top: 10px; opacity: 0.8; }
        
        .section {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;
        }
        .section h2 { margin-bottom: 20px; font-size: 22px; }
        
        .grades-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;
        }
        
        .grade-card {
            border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px; transition: all 0.3s;
        }
        .grade-card:hover { border-color: #667eea; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        .grade-card h3 { font-size: 18px; margin-bottom: 8px; }
        .grade-card .course { color: #6b7280; font-size: 14px; margin-bottom: 15px; }
        
        .grade-display {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px; background: #f9fafb; border-radius: 8px; margin-bottom: 15px;
        }
        .grade-score {
            font-size: 32px; font-weight: 700;
        }
        .grade-max { color: #6b7280; font-size: 14px; }
        
        .grade-stats {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
        }
        .stat-item { font-size: 13px; color: #6b7280; }
        .stat-item strong { color: #1a1a1a; display: block; margin-top: 4px; }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f9fafb; padding: 15px; text-align: left; font-weight: 600;
            color: #374151; font-size: 13px; text-transform: uppercase;
        }
        td { padding: 15px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
        tr:hover { background: #f9fafb; }
        
        .score-badge {
            display: inline-block; padding: 6px 12px; border-radius: 20px;
            font-weight: 700; font-size: 14px;
        }
        
        .empty-state {
            text-align: center; padding: 60px 20px; color: #9ca3af;
        }
        .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .grades-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>⭐ My Grades</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <div class="gpa-card">
                <div class="gpa-label">Overall GPA</div>
                <div class="gpa-value"><?php echo number_format($gpa, 2); ?></div>
                <div class="gpa-scale">out of 100.00</div>
            </div>
            
            <div class="section">
                <h2>📊 Course Grades</h2>
                <?php if ($course_grades->num_rows > 0): ?>
                    <div class="grades-grid">
                        <?php while ($course = $course_grades->fetch_assoc()): ?>
                            <div class="grade-card">
                                <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                <p class="course">
                                    <?php echo htmlspecialchars($course['course_code']); ?> • 
                                    <?php echo htmlspecialchars($course['instructor_name']); ?>
                                </p>
                                
                                <?php if ($course['final_grade']): ?>
                                    <div class="grade-display">
                                        <div>
                                            <span class="score-badge" style="background: <?php echo getGradeColor($course['final_grade']); ?>; color: white;">
                                                <?php echo number_format($course['final_grade'], 1); ?>
                                            </span>
                                            <div class="grade-max">Final Grade</div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 24px; font-weight: 700; color: #1a1a1a;">
                                                <?php 
                                                if ($course['final_grade'] >= 80) echo 'A';
                                                elseif ($course['final_grade'] >= 70) echo 'B';
                                                elseif ($course['final_grade'] >= 60) echo 'C';
                                                elseif ($course['final_grade'] >= 50) echo 'D';
                                                else echo 'F';
                                                ?>
                                            </div>
                                            <div class="grade-max">Letter Grade</div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="grade-display" style="justify-content: center;">
                                        <span style="color: #6b7280;">Not graded yet</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="grade-stats">
                                    <div class="stat-item">
                                        Progress
                                        <strong><?php echo number_format($course['progress'], 0); ?>%</strong>
                                    </div>
                                    <div class="stat-item">
                                        Assignments Graded
                                        <strong><?php echo $course['total_graded']; ?></strong>
                                    </div>
                                    <?php if ($course['avg_assignment_score']): ?>
                                        <div class="stat-item" style="grid-column: 1 / -1;">
                                            Average Assignment Score
                                            <strong><?php echo number_format($course['avg_assignment_score'], 1); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📚</div>
                        <p>No courses enrolled yet</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2>📝 Assignment Grades</h2>
                <?php if ($graded_assignments->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Course</th>
                                    <th>Score</th>
                                    <th>Submitted</th>
                                    <th>Graded</th>
                                    <th>Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($graded_assignments, 0); ?>
                                <?php while ($assignment = $graded_assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($assignment['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                        <td>
                                            <span class="score-badge" style="background: <?php echo getGradeColor(($assignment['score']/$assignment['max_score'])*100); ?>; color: white;">
                                                <?php echo number_format($assignment['score'], 1); ?> / <?php echo $assignment['max_score']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($assignment['submitted_at'])); ?></td>
                                        <td><?php echo date('d M Y', strtotime($assignment['graded_at'])); ?></td>
                                        <td>
                                            <?php if ($assignment['feedback']): ?>
                                                <?php echo htmlspecialchars(substr($assignment['feedback'], 0, 50)); ?>
                                                <?php echo strlen($assignment['feedback']) > 50 ? '...' : ''; ?>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <p>No graded assignments yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>