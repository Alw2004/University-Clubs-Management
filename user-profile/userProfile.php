<?php
include ("../connect.php");
session_start();

function verifyRole($role){
    if($role=="1"){
        return "Inscrit";
    }else if($role=="2"){
        return "Membre";
    }else if($role=="3"){
        return "Responsable";
    }else if($role=="4"){
        return "Chargé";
    }else{
        return "Inconnu";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile Template</title>
    <link rel="stylesheet" href="userProfile_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="../Notifications/notification_script.js"></script>
</head>

<body>
<form form method="post" action="updateProfile.php">
    <div class="container light-style flex-grow-1 container-p-y">
        <h4 class="font-weight-bold py-3 mb-4">
            Account settings
        </h4>
        <div class="card overflow-hidden">
            <div class="row no-gutters row-bordered row-border-light">
                <div class="col-md-3 pt-0">
                    <div class="list-group list-group-flush account-settings-links">
                        <a class="list-group-item list-group-item-action active" data-toggle="list"
                            href="#account-general">General</a>
                        <a class="list-group-item list-group-item-action" data-toggle="list"
                            href="#account-change-password">Change password</a>
                        <a class="list-group-item list-group-item-action" data-toggle="list"
                            href="#account-info">Info</a>

                    </div>
                </div>
                <div class="col-md-9">
                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="account-general">
                            <div class="card-body media align-items-center">
                                <img src="../images/pfp.png" alt
                                    class="d-block ui-w-80">
                                <div class="media-body ml-4">
                                    <label class="btn btn-outline-primary">
                                        Upload new photo
                                        <input type="file" class="account-settings-fileinput">
                                    </label> &nbsp;
                                    <button type="button" class="btn btn-default md-btn-flat">Reset</button>
                                    <div class="text-light small mt-1">Allowed JPG, GIF or PNG. Max size of 800K</div>
                                </div>
                            </div>
                            <hr class="border-light m-0">
                            <div class="card-body">
                               
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="Username" class="form-control mb-1" value="<?php echo $_SESSION['user_username']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo $_SESSION['user_name']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">E-mail</label>
                                    <input type="text" name="email" class="form-control mb-1" value="<?php echo $_SESSION['user_email']; ?>" readonly>
                                </div>
                              
                            </div>
                        </div>
                        <div class="tab-pane fade" id="account-change-password">
                            <div class="card-body pb-2">
                                <div class="form-group">
                                    <label class="form-label">Current password</label>
                                    <input type="text" class="form-control" value="<?php echo $_SESSION['user_password']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">New password</label>
                                    <input type="password" name="newPassword" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Repeat new password</label>
                                    <input type="password" name="cnewPassword" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="account-info">
                            <div class="card-body pb-2">

                                <div class="form-group">
                                    <label class="form-label">Birthday</label>
                                    <input type="date" name="birthday" class="form-control" value="<?php echo $_SESSION['user_birthday']; ?>">
                                </div>

                            </div>
                            <hr class="border-light m-0">
                            <div class="card-body pb-2">

                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo $_SESSION['user_phone']; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Role</label>
                                    <input type="text" name="role" class="form-control" value="<?php echo verifyRole($_SESSION['user_role']); ?>" readonly>
                                    
                                </div>

                                
                            </div>
                        </div>



                    </div>
                </div>
            </div>
        </div>
        <div class="text-right mt-3">
            <button type="submit" name="saveProfile" class="btn btn-primary">Save changes</button>
            <a href="../homepage.php">
            <button type="button" class="btn btn-default">Cancel</button>
            </a>
        </div>
    </div>
</form>
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="https://code.jquery.com/jquery-1.10.2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript">

    </script>
</body>

</html>