<?php
include ("connect.php");
session_start();

if (isset($_GET['id'])) {
    $clubId = intval($_GET['id']); // always sanitize input
    $_SESSION['club_id'] = $clubId; // Store the club ID in session for later use
    $query = "SELECT * FROM clubs WHERE id = $clubId";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $club = mysqli_fetch_assoc($result);
    } else {
        echo "Club not found.";
        exit;
    }
} else {
    echo "No club ID provided.";
    exit;
}

//Member list
$Mquery = "SELECT *
           FROM clubmembers
           JOIN users
           ON clubmembers.userid = users.id 
           AND clubmembers.clubid = $clubId";

$reqQuery = "SELECT * FROM creationrequests";

//  Execute the query
$memberList = $conn->query($Mquery);
$requestsList = $conn->query($reqQuery);
// Affichage de Responsable
$Rquery = "SELECT * FROM users WHERE id = (SELECT resId FROM clubs WHERE id = $clubId)";
$Rresult = mysqli_query($conn, $Rquery);
if ($Rresult && mysqli_num_rows($Rresult) > 0) {
    $responsable = mysqli_fetch_assoc($Rresult);
} else {
    echo "Responsable not found.";
    exit;
}
// Handle approving/denying requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['reqApprove']) || isset($_POST['reqDeny']))) {
    if (isset($_POST['req'])) {

        $userid = mysqli_real_escape_string($conn, $_POST['req']);


        if (isset($_POST['reqApprove'])) {
            $query1 = "INSERT INTO clubs (resid) VALUES ('$userid')";
            $query2 = "DELETE FROM creationrequests WHERE userid = '$userid'";
            $query3 = "UPDATE users SET role = 3 WHERE id = '$userid'";

            try {
                $conn->query($query1);
                $conn->query($query2);
                $conn->query($query3);
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
                exit;
            }
        } elseif (isset($_POST['reqDeny'])) {
            $query = "DELETE FROM creationrequests WHERE userid = '$userid'";
            try {
                $conn->query($query);
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
                exit;
            }
        }

        header("Location: liste_membres.php");
        exit();
    } else {
        echo "User ID not provided.";
        exit;
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
</head>

<body>
<form method="POST" action="">
    <div class="container light-style flex-grow-1 container-p-y">
        <h4 class="font-weight-bold py-3 mb-4">
            Club Details:
        </h4>
        <div class="card overflow-hidden">
            <div class="row no-gutters row-bordered row-border-light">
                <div class="col-md-3 pt-0">
                    <div class="list-group list-group-flush account-settings-links">
                        <a class="list-group-item list-group-item-action active" data-toggle="list"
                            href="#account-general">General</a> 
                        <a class="list-group-item list-group-item-action" data-toggle="list"
                            href="#account-change-password">Members list</a>
                        <?php
                            if($_SESSION['user_role']<3){
                                echo '<a class="list-group-item list-group-item-action" data-toggle="list"
                                href="#create-club">Create your own club!</a>';
                            }elseif($_SESSION['user_role']>3){
                                echo '<a class="list-group-item list-group-item-action" data-toggle="list"
                                href="#club-request">Club requests</a>';
                            }
                            ?>

                    </div>
                </div>
                <div class="col-md-9">
                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="account-general">
                            <div class="card-body media align-items-center">
                                <img src="images/club.png" alt
                                    class="d-block ui-w-80">
                                    
                            </div>
                            <hr class="border-light m-0">
                            <div class="card-body">
                               
                                <div class="form-group">
                                    <label class="form-label">Club Name:</label>
                                    <input type="text" name="clubname" class="form-control mb-1" value="<?php echo $club['name']; ?>" <?php if($_SESSION['user_role']<4){echo "readonly";}?>>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="clubDesc" class="form-control" value="<?php echo $club['description']; ?>" <?php if($_SESSION['user_role']<3 && $_SESSION['user_id']!=$responsable['id']){echo "readonly";}?>>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Responsable</label>
                                    <input type="text" name="email" class="form-control mb-1" value="<?php echo "Name: ".$responsable['name']."      Email: ".$responsable['email'] ?>" <?php if($_SESSION['user_role']<3){echo "readonly";}?>>
                                </div>
                              
                            </div>
                        </div>
                        <div class="tab-pane fade" id="account-change-password">
                            <div class="card-body pb-2">
                                <div class="form-group">
                                    <label class="form-label">Members</label>
                                    <?php 
            if ($memberList && $memberList->num_rows > 0) {
                echo '<ul>'; // Start the unordered list
                while ($row = $memberList->fetch_assoc()) {
                    // Create a list item for each member
                    echo '<li>';
                    echo "Nom: " . htmlspecialchars($row['name']) . " - ";
                    echo "Email: " . htmlspecialchars($row['email']) . " - ";
                    echo "Role: " . htmlspecialchars($row['crole']) . " - ";
                    echo "Joined in: " . htmlspecialchars($row['date']);
                    echo '</li>';
                }
                echo '</ul>'; // End the unordered list
            } else {
                echo "Aucun membre trouvé pour ce club.";
            }
            ?>
                                </div>

                            </div>
                        </div>

                        
                        <div class="tab-pane fade" id="create-club">
                            <div class="card-body pb-2">

                                <div class="form-group">
                                    <label class="form-label">Club Details  Format: Name:... / Description:...</label>
                                    <input type="text" name="clubreq" class="form-control" placeholder="Format: Name:... / Description:...</label>">
                                </div>
                            </div>
                            <hr class="border-light m-0">
                            <div class="card-body pb-2">    
                                </div>
                            </div>
                            <div class="tab-pane fade" id="club-request">
                            <div class="card-body pb-2">

                                <div class="form-group">
                                    <label class="form-label">requests</label>
                                    <?php 
            if ($requestsList && $requestsList->num_rows > 0) {
                echo '<ul>'; // Start the unordered list
                while ($row = $requestsList->fetch_assoc()) {
                    // Create a list item for each member
                    echo '<li>';
                    echo "UserId: " . htmlspecialchars($row['userid']) . " - ";
                    echo "Description: " . htmlspecialchars($row['description']) . " - ";
                    echo "Date: " . htmlspecialchars($row['date']) . " - ";
                    ?>
                    <br>
<form method="post" action="">
    <input type="hidden" name="req" value="<?php echo htmlspecialchars($row['userid']); ?>">
    <button type="submit" name="reqApprove" class="btn btn-sm btn-success">Approve</button>
    <button type="submit" name="reqDeny" class="btn btn-sm btn-danger">Deny</button>
</form>
<br><br>

                    <?php
                    echo '</li>';
                }
                echo '</ul>'; // End the unordered list
            } else {
                echo "No requests.";
            }
            ?>
                                </div>
                            </div>
                            <hr class="border-light m-0">
                            <div class="card-body pb-2">    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-right mt-3">
            <button type="submit" name="saveClubProfile" class="btn btn-primary">Save changes</button>
            <a href="homepage.php">
            <button type="button" class="btn btn-default">Cancel</button>
            </a>
        </div>
    </div>
</form>
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="https://code.jquery.com/jquery-1.10.2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript"></script>
    
</body>

</html>