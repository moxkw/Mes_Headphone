<?php
// FILE: logout.php - đăng xuất: xóa session rồi về trang login
session_start();
session_destroy();
header('Location: login.php');
exit;
