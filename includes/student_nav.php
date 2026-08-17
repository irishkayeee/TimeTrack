<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<div class="row">
    <div class="col-xl-2 col-lg-3 mb-3">
        <div class="card rounded-4 shadow-sm p-3 h-100">
            <h6 class="mb-3">Student Menu</h6>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action<?php echo $currentPage === 'dashboard.php' ? ' active' : ''; ?>">Dashboard</a>
                <a href="../logout.php" class="list-group-item list-group-item-action">Logout</a>
            </div>
        </div>
    </div>
    <div class="col-xl-10 col-lg-9">
