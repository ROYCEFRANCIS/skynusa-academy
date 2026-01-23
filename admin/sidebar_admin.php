<?php
// Admin Sidebar Component
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$admin_name = $_SESSION['full_name'] ?? 'Admin';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h2>🎓 SKYNUSA</h2>
            <p>Academy Admin</p>
        </div>
    </div>
    
    <div class="admin-profile">
        <div class="avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($admin_name); ?></h3>
            <p>Administrator</p>
        </div>
    </div>
    
    <nav class="nav-menu">
        <div class="nav-section">
            <div class="nav-section-title">Main Menu</div>
            <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="courses.php" class="nav-item <?php echo $current_page == 'courses' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
            <a href="enrollments.php" class="nav-item <?php echo $current_page == 'enrollments' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Enrollments</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Users</div>
            <a href="students.php" class="nav-item <?php echo $current_page == 'students' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a href="instructors.php" class="nav-item <?php echo $current_page == 'instructors' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Instructors</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Settings</div>
            <a href="settings.php" class="nav-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="reports.php" class="nav-item <?php echo $current_page == 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
        
        <div class="nav-section nav-footer">
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</aside>

<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 280px;
    background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
    color: white;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header .logo h2 {
    color: white;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 5px;
}

.sidebar-header .logo p {
    color: #94a3b8;
    font-size: 12px;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: rgba(255,255,255,0.05);
    margin: 15px;
    border-radius: 12px;
}

.admin-profile .avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
}

.admin-profile .profile-info h3 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 3px;
}

.admin-profile .profile-info p {
    font-size: 12px;
    color: #94a3b8;
}

.nav-menu {
    padding: 10px 0;
}

.nav-section {
    margin-bottom: 20px;
}

.nav-section-title {
    padding: 10px 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #cbd5e1;
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
}

.nav-item i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.nav-item:hover {
    background: rgba(255,255,255,0.08);
    color: white;
}

.nav-item.active {
    background: rgba(59, 130, 246, 0.15);
    color: white;
    border-left: 3px solid #3b82f6;
}

.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #3b82f6;
}

.nav-footer {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar-header .logo p,
    .admin-profile .profile-info,
    .nav-section-title,
    .nav-item span {
        display: none;
    }
    
    .admin-profile {
        justify-content: center;
        padding: 15px;
    }
    
    .nav-item {
        justify-content: center;
    }
    
    .nav-item i {
        margin-right: 0;
    }
}
</style>