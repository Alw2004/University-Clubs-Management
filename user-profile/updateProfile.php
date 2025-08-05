<?php
include '../connect.php';
session_start();

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['saveProfile'])){
    if(isset($_POST['Username']) && 
       isset($_POST['name']) && 
       isset($_POST['email'])){
    $username=$_POST['Username'];
    $name=$_POST['name'];

    $birthday=$_POST['birthday'];
    $phone=$_POST['phone'];
    
    }
    if(isset($_POST['newPassword']) && isset($_POST['cnewPassword'])){
        if($_POST['newPassword'] != $_POST['cnewPassword']) {
            echo "New password and confirm password do not match.";
            exit();
        } else {
            $newPassword = $_POST['newPassword'];
            $newPassword = md5($newPassword);
        }}

    $insertQuery = "UPDATE users SET name='$name', password='$newPassword', username='$username', birthday='$birthday', phone='$phone' WHERE id='".$_SESSION['user_id']."'";
    try {
        $result=$conn->query($insertQuery);
        if($result){
            echo "Profile updated successfully";
        
          
            $sql = "SELECT * FROM users WHERE id='" . $_SESSION['user_id'] . "'";

            $result=$conn->query($sql);
            if($result->num_rows>0){
             $row=$result->fetch_assoc();
        
             $_SESSION['user_name']=$row['name'];
             $_SESSION['user_password']=$row['password'];
             $_SESSION['user_role']=$row['role'];
             $_SESSION['user_username']=$row['username'];
             $_SESSION['user_birthday']=$row['birthday'];
             $_SESSION['user_phone']=$row['phone'];
            }

        } else {
            echo "Error updating profile: " . $conn->error;
        }
        header("Location: ../homepage.php");
        exit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }

}
?>