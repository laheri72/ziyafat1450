<?php
require_once '../includes/functions.php';
init_session();
session_unset();
session_destroy();
header('Location: ../index.php');
exit();
?>