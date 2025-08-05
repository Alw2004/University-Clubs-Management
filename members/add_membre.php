<?php
include '../connect.php';

/**
 * Adds a user to a club and notifies them of acceptance.
 * @param int $clubid The ID of the club
 * @param int $userid The ID of the student
 * @param string $role The role within the club (e.g., 'member')
 * @return bool Success status
 */
function addMemberToClub($clubid, $userid, $role) {
    global $conn;

    // Insert into clubmembers
    $stmt = $conn->prepare(
        "INSERT INTO clubmembers (clubid, userid, crole, date) 
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param("iis", $clubid, $userid, $role);
    $success = $stmt->execute();
    $stmt->close();

    if (!$success) {
        return false; // Failed to add to club
    }

    // Get club name
    $stmt = $conn->prepare("SELECT name FROM clubs WHERE id = ?");
    $stmt->bind_param("i", $clubid);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();
    $stmt->close();

    $clubName = $club['name'];

    // Insert notification for the student
    $message = "You have been added to club '$clubName' as '$role'.";
    $type = 'club_join_accepted';

    $stmt = $conn->prepare(
        "INSERT INTO notifications (userid, message, type, is_read, created_at) 
         VALUES (?, ?, ?, 0, NOW())"
    );
    $stmt->bind_param("iss", $userid, $message, $type);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

// Example usage (e.g., called by club responsible via a form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_member'])) {
    $clubid = (int)$_POST['clubid'];
    $userid = (int)$_POST['userid'];
    $role = 'member'; // Default role, could be from form
    if (addMemberToClub($clubid, $userid, $role)) {
        echo "Member added and notified!";
    } else {
        echo "Error adding member.";
    }
}
?>