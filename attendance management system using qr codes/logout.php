<?php
require_once __DIR__ . '/includes/auth.php';
logoutUser();
flash('You have been logged out.','success');
redirect('index.php');
