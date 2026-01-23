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

// Handle Enrollment
if (isset($_POST['enroll'])) {
    $course_id = (int)$_POST['course_id'];
    
    // Check if already enrolled
    $check = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=?");
    $check->bind_param("ii", $student_id, $course_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $error = 'Anda sudah terdaftar di kursus ini!';
    } else {
        // Check quota
        $quota_check = $conn->query("
            SELECT c.quota, COUNT(e.id) as enrolled
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.status='active'
            WHERE c.id = $course_id
            GROUP BY c.id
        ");
        $quota_data = $quota_check->fetch_assoc();
        
        if ($quota_data['enrolled'] >= $quota_data['quota']) {
            $error = 'Maaf, kuota kursus sudah penuh!';
        } else {
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status, progress) VALUES (?, ?, 'active', 0)");
            $stmt->bind_param("ii", $student_id, $course_id);
            
            if ($stmt->execute()) {
                $success = 'Berhasil mendaftar kursus!';
            } else {
                $error = 'Gagal mendaftar kursus!';
            }
        }
    }
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

$where = "c.status = 'active'";
if ($search) {
    $search = $conn->real_escape_string($search);
    $where .= " AND (c.course_name LIKE '%$search%' OR c.course_code LIKE '%$search%' OR c.description LIKE '%$search%')";
}
if ($category) {
    $category = $conn->real_escape_string($category);
    $where .= " AND c.category = '$category'";
}

// Get available courses
$courses = $conn->query("
    SELECT c.*,
           u.full_name as instructor_name,
           COUNT(DISTINCT e.id) as enrolled_count,
           EXISTS(SELECT 1 FROM enrollments WHERE student_id='$student_id' AND course_id=c.id) as is_enrolled
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status='active'
    WHERE $where
    GROUP BY c.id
    ORDER BY c.created_at DESC
");

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' ORDER BY category");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Courses - Skynusa Academy</title>
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
        
        .search-bar {
            background: white; padding: 25px; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .search-form {
            display: grid; grid-template-columns: 1fr auto auto; gap: 15px;
        }
        .search-input {
            padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 8px;
            font-size: 15px; transition: all 0.3s;
        }
        .search-input:focus { outline: none; border-color: #667eea; }
        
        .courses-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px;
        }
        
        .course-card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;
            border-left: 4px solid #667eea; position: relative;
        }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        
        .course-header {
            display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;
        }
        .course-code {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        
        .course-card h3 { font-size: 20px; margin-bottom: 10px; color: #1a1a1a; }
        .course-card .instructor {
            color: #6b7280; font-size: 14px; margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .course-card .description {
            color: #6b7280; font-size: 14px; line-height: 1.6; margin-bottom: 20px;
        }
        
        .course-meta {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
            margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 8px;
        }
        .meta-item {
            display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6b7280;
        }
        
        .course-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 20px; border-top: 1px solid #e5e7eb;
        }
        
        .quota-badge {
            padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .quota-available { background: #d1fae5; color: #065f46; }
        .quota-limited { background: #fef3c7; color: #92400e; }
        .quota-full { background: #fee2e2; color: #991b1b; }
        
        .enrolled-badge {
            position: absolute; top: 20px; right: 20px;
            background: #10b981; color: white; padding: 6px 12px;
            border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        
        .empty-state {
            text-align: center; padding: 80px 20px; background: white;
            border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .courses-grid { grid-template-columns: 1fr; }
            .search-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>🔍 Browse Courses</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="search-bar">
                <form method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" 
                           placeholder="🔍 Search courses by name, code, or description..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category" class="search-input" style="max-width: 200px;">
                        <option value="">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
            
            <?php if ($courses->num_rows > 0): ?>
                <div class="courses-grid">
                    <?php while ($course = $courses->fetch_assoc()): 
                        $quota_left = $course['quota'] - $course['enrolled_count'];
                        $quota_percentage = ($course['enrolled_count'] / $course['quota']) * 100;
                    ?>
                        <div class="course-card">
                            <?php if ($course['is_enrolled']): ?>
                                <span class="enrolled-badge">✓ Enrolled</span>
                            <?php endif; ?>
                            
                            <div class="course-header">
                                <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                            </div>
                            
                            <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                            <p class="instructor">👨‍🏫 <?php echo htmlspecialchars($course['instructor_name']); ?></p>
                            <p class="description"><?php echo htmlspecialchars(substr($course['description'], 0, 120)); ?>...</p>
                            
                            <div class="course-meta">
                                <div class="meta-item">
                                    <span>📚</span>
                                    <span><?php echo htmlspecialchars($course['category']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span>⏱️</span>
                                    <span><?php echo htmlspecialchars($course['duration']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span>👥</span>
                                    <span><?php echo $course['enrolled_count']; ?> / <?php echo $course['quota']; ?> students</span>
                                </div>
                                <div class="meta-item">
                                    <span>📅</span>
                                    <span><?php echo date('d M Y', strtotime($course['start_date'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="course-footer">
                                <?php if ($quota_percentage >= 100): ?>
                                    <span class="quota-badge quota-full">Full</span>
                                <?php elseif ($quota_percentage >= 80): ?>
                                    <span class="quota-badge quota-limited"><?php echo $quota_left; ?> seats left</span>
                                <?php else: ?>
                                    <span class="quota-badge quota-available">Available</span>
                                <?php endif; ?>
                                
                                <?php if ($course['is_enrolled']): ?>
                                    <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-secondary btn-sm">
                                        View Course →
                                    </a>
                                <?php elseif ($quota_percentage >= 100): ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Full</button>
                                <?php else: ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" name="enroll" class="btn btn-success btn-sm" 
                                                onclick="return confirm('Daftar kursus ini?')">
                                            Enroll Now →
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h2>No Courses Found</h2>
                    <p>Try adjusting your search or filters</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>