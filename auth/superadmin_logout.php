<?php
session_start();
session_destroy();
header('Location: ../auth/superadmin_login.php');
exit;
