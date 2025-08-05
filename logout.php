<?php
include("connect.php");
session_destroy();
header("location: index.php");
//closing connection
$conn->close();
?>