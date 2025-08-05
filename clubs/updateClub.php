<?php
include '../connect.php';
session_start();

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['saveClubProfile'])){
    if(isset($_POST['clubname']) && 
       isset($_POST['clubDesc'])){


        $insertQuery = "UPDATE clubs SET name='".$_POST['clubname']."',description='".$_POST['clubDesc']."'  WHERE id='".$_SESSION['club_id']."'";
    try {
        $result=$conn->query($insertQuery);
        if($result){
            echo "Club updated successfully";
        } else {
            echo "Error updating Club: " . $conn->error;
        }
        header("Location: ../homepage.php");
        exit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }

}}
?>