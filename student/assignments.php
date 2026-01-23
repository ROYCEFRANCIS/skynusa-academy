<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

$success = '';
$error = '';

// Handle submission
if (isset($_POST['submit_assignment'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $notes = $_POST['notes'];
    $file_url = $_POST['file_url'];
    
    // Check if already submitted
    $check = $conn->prepare("SELECT id FROM assignment_submissions WHERE assignment_id=? AND student_id=?");
    $check->bind_param("ii", $assignment_id, $student_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing submission
        $stmt = $conn->prepare("UPDATE assignment_submissions SET file_url=?, notes=?, submitted_at=NOW() WHERE assignment_id=? AND student_id=?");
        $stmt->bind_param("ssii", $file_url, $notes, $assignment_id, $student_id);
    } else {
        // New submission
        $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_url, notes, status) VALUES (?, ?, ?, ?, 'submitted')");
        $stmt->bind_param("iiss", $assignment_id, $student_id, $file_url, $notes);
    }
    
    if ($stmt->execute()) {
        $success = 'Assignment submitted successfully!';
    } else {
        $error = 'Failed to submit assignment!';
    }
}

// Get all assignments from enrolled courses
$assignments = $conn->query("
    SELECT a.*, c.course_name, c.course_code,
           s.id as submission_id, s.file_url as submitted_file, s.notes as submission_notes,
           s.score, s.feedback, s.status as submission_status, s.submitted_at,
           CASE 
               WHEN a.due_date < NOW() THEN 'overdue'
               WHEN DATEDIFF(a.due_date, NOW()) <= 3 THEN 'due_soon'
               ELSE 'active'
           END as urgency
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id AND e.student_id = '$student_id'
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = '$student_id'
    WHERE a.status = 'active'
    ORDER BY a.due_date ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Skynusa Academy</title>
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
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #e5e7eb; color: #4b5563; }
        .btn-success { background: #10b981; color: white; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .alert {
            padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        .assignments-grid {
            display: grid; gap: 20px;
        }
        
        .assignment-card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;
            border-left: 4px solid #667eea;
        }
        .assignment-card.overdue { border-left-color: #ef4444; }
        .assignment-card.due_soon { border-left-color: #f59e0b; }
        .assignment-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        
        .assignment-header {
            display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;
        }
        
        .assignment-card h3 { font-size: 20px; margin-bottom: 8px; color: #1a1a1a; }
        .assignment-card .course { color: #6b7280; font-size: 14px; margin-bottom: 15px; }
        .assignment-card .description { color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
        
        .assignment-meta {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;
            margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 8px;
        }
        .meta-item {
            display: flex; flex-direction: column; gap: 5px;
        }
        .meta-label { font-size: 12px; color: #6b7280; font-weight: 600; }
        .meta-value { font-size: 14px; color: #1a1a1a; font-weight: 500; }
        
        .submission-form {
            padding: 20px; background: #f9fafb; border-radius: 8px; margin-top: 20px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 12px; border: 2px solid #e5e7eb;
            border-radius: 8px; font-size: 14px;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        
        .badge {
            padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-submitted { background: #dbeafe; color: #1e40af; }
        .badge-graded { background: #d1fae5; color: #065f46; }
        .badge-overdue { background: #fee2e2; color: #991b1b; }
        .badge-due-soon { background: #fef3c7; color: #92400e; }
        .badge-active { background: #e5e7eb; color: #4b5563; }
        
        .score-display {
            font-size: 24px; font-weight: 700; color: #10b981;
            text-align: center; padding: 15px; background: #f0fdf4;
            border-radius: 8px; margin-top: 15px;
        }
        
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
                <h1>📝 My Assignments</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($assignments->num_rows > 0): ?>
                <div class="assignments-grid">
                    <?php while ($assignment = $assignments->fetch_assoc()): ?>
                        <div class="assignment-card <?php echo $assignment['urgency']; ?>">
                            <div class="assignment-header">
                                <div style="flex: 1;">
                                    <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                    <p class="course">
                                        📚 <?php echo htmlspecialchars($assignment['course_code']); ?> - 
                                        <?php echo htmlspecialchars($assignment['course_name']); ?>
                                    </p>
                                </div>
                                <?php if ($assignment['submission_status'] == 'graded'): ?>
                                    <span class="badge badge-graded">✅ Graded</span>
                                <?php elseif ($assignment['submission_status'] == 'submitted'): ?>
                                    <span class="badge badge-submitted">📤 Submitted</span>
                                <?php elseif ($assignment['urgency'] == 'overdue'): ?>
                                    <span class="badge badge-overdue">⏰ Overdue</span>
                                <?php elseif ($assignment['urgency'] == 'due_soon'): ?>
                                    <span class="badge badge-due-soon">⚠️ Due Soon</span>
                                <?php else: ?>
                                    <span class="badge badge-active">📋 Active</span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description"><?php echo htmlspecialchars($assignment['description']); ?></p>
                            
                            <div class="assignment-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Due Date</span>
                                    <span class="meta-value">
                                        <?php echo date('d M Y, H:i', strtotime($assignment['due_date'])); ?>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Max Score</span>
                                    <span class="meta-value"><?php echo $assignment['max_score']; ?> points</span>
                                </div>
                                <?php if ($assignment['submitted_at']): ?>
                                    <div class="meta-item">
                                        <span class="meta-label">Submitted</span>
                                        <span class="meta-value">
                                            <?php echo date('d M Y, H:i', strtotime($assignment['submitted_at'])); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($assignment['file_url']): ?>
                                <a href="<?php echo htmlspecialchars($assignment['file_url']); ?>" target="_blank" 
                                   class="btn btn-secondary btn-sm" style="margin-bottom: 15px;">
                                    📥 Download Assignment File
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($assignment['submission_status'] == 'graded'): ?>
                                <div class="score-display">
                                    Score: <?php echo number_format($assignment['score'], 1); ?> / <?php echo $assignment['max_score']; ?>
                                </div>
                                <?php if ($assignment['feedback']): ?>
                                    <div style="margin-top: 15px; padding: 15px; background: #f0fdf4; border-radius: 8px;">
                                        <strong>Feedback:</strong>
                                        <p style="margin-top: 8px; color: #065f46;">
                                            <?php echo nl2br(htmlspecialchars($assignment['feedback'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($assignment['submitted_file']): ?>
                                    <a href="<?php echo htmlspecialchars($assignment['submitted_file']); ?>" target="_blank" 
                                       class="btn btn-secondary btn-sm" style="margin-top: 15px;">
                                        📄 View My Submission
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="submission-form">
                                    <h4 style="margin-bottom: 15px;">
                                        <?php echo $assignment['submission_id'] ? '✏️ Update Submission' : '📤 Submit Assignment'; ?>
                                    </h4>
                                    <form method="POST">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                        
                                        <div class="form-group">
                                            <label>File URL (Google Drive, Dropbox, etc.)</label>
                                            <input type="url" name="file_url" 
                                                   value="<?php echo htmlspecialchars($assignment['submitted_file'] ?? ''); ?>" 
                                                   placeholder="https://..." required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Notes (optional)</label>
                                            <textarea name="notes" placeholder="Add any notes or comments about your submission..."><?php echo htmlspecialchars($assignment['submission_notes'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <button type="submit" name="submit_assignment" class="btn btn-success">
                                            <?php echo $assignment['submission_id'] ? '🔄 Update Submission' : '📤 Submit Assignment'; ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h2>No Assignments</h2>
                    <p>You don't have any assignments at the moment</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>