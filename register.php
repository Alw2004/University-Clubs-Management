<?php 

include 'connect.php';

if(isset($_POST['signUp'])){
    $name=$_POST['fName'];
    $username=$_POST['lName'];
    $email=$_POST['email'];
    $password=$_POST['password'];
    $password=md5($password);

     $checkEmail= "SELECT * From users where email='$email'";
     $result=$conn->query($checkEmail);
     if($result->num_rows>0){
        echo "Email Address Already Exists !";
     }
     else{
        $insertQuery= "INSERT INTO users(email, password, name, username)
                       VALUES ('$email','$password', '$name' , '$username')"; 
            if($conn->query($insertQuery)==TRUE){
                header("location: index.php");
            }
            else{
                echo "Error:".$conn->error;
            }
     }
   

}

if(isset($_POST['signIn'])){
   $email=$_POST['email'];
   $password=$_POST['password'];
   $password=md5($password) ;
   
   $sql="SELECT * FROM users WHERE email='$email' and password='$password'";
   $result=$conn->query($sql);
   if($result->num_rows>0){
    session_start();
    $row=$result->fetch_assoc();
    
    $_SESSION['user_id']=$row['id'];
    $_SESSION['user_name']=$row['name'];
    $_SESSION['user_username']=$row['username'];
    $_SESSION['user_email']=$row['email'];
    $_SESSION['user_password']=$row['password'];
    $_SESSION['user_role']=$row['role'];
    $_SESSION['user_birthday']=$row['birthday'];
    $_SESSION['user_phone']=$row['phone'];


    header("Location: homepage.php");
    exit();
   }
   else{
    echo "Not Found, Incorrect Email or Password";
   }

}
?>