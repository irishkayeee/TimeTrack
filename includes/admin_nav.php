<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<div class="row">
    <div class="col-xl-2 col-lg-3 mb-3">
        <div class="card rounded-4 shadow-sm p-3 h-100">
            <h6 class="mb-3">Admin Menu</h6>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'dashboard.php' ? ' active' : ''; ?>">Dashboard</a>
                <a href="students.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'students.php' ? ' active' : ''; ?>">Students</a>
                <a href="teachers.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'teachers.php' ? ' active' : ''; ?>">Teachers</a>
                <a href="attendance.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'attendance.php' ? ' active' : ''; ?>">Attendance</a>
                <a href="qr-generator.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'qr-generator.php' ? ' active' : ''; ?>">QR Generator</a>
                <a href="scanner.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'scanner.php' ? ' active' : ''; ?>">Scanner</a>
                <a href="courses.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'courses.php' ? ' active' : ''; ?>">Courses</a>
                <a href="rooms.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'rooms.php' ? ' active' : ''; ?>">Rooms</a>
                <a href="subjects.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'subjects.php' ? ' active' : ''; ?>">Subjects</a>
                <a href="reports.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'reports.php' ? ' active' : ''; ?>">Reports</a>
                <a href="settings.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'settings.php' ? ' active' : ''; ?>">Settings</a>
            </div>
        </div>
    </div>
    <div class="col-xl-10 col-lg-9">
