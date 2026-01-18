<?php
session_start();
session_destroy();
header("Location: /hospital/auth/login.php");
exit;
