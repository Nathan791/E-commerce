<?php
SESSION_start();
session_destroy();
header("Location: /COMMERCE/E-commerce-main/Login.php");
?>