<?php
include '../connect.php';

/**
 * Sends a notification to the club responsible when a student requests to join.
 * @param int $userid The ID of the student requesting to join
 * @param int $clubid The ID of the club
 * @return bool Success status
 */
function requestToJoinClub($userid, $clubid) {
    global $conn;

    // Get club details and responsible ID
    $stmt = $conn->prepare("SELECT name, resId FROM clubs WHERE id = ?");
    $stmt->bind_param("i", $clubid);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();
    $stmt->close();

    if (!$club) {
        return false; // Club not found
    }

    $resId = $club['resId'];
    $clubName = $club['name'];

    // Get student username
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $username = $user['username'] ?? "User ID $userid";

    // Insert notification for the club responsible
    $message = "User '$username' has requested to join your club '$clubName'.";
    $type = 'club_join_request';

    $stmt = $conn->prepare(
        "INSERT INTO notifications (userid, message, type, is_read, created_at) 
         VALUES (?, ?, ?, 0, NOW())"
    );
    $stmt->bind_param("iss", $resId, $message, $type);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

// Example usage (e.g., called from a form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_club'])) {
    $userid = (int)$_POST['userid']; // Assume from logged-in session
    $clubid = (int)$_POST['clubid'];
    if (requestToJoinClub($userid, $clubid)) {
        echo "Join request sent successfully!";
    } else {
        echo "Error sending join request.";
    }
}
?>