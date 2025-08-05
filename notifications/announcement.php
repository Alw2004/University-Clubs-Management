<?php
include '../connect.php';

/**
 * Posts an announcement and notifies relevant users.
 * @param int $clubid The ID of the club
 * @param string $title Announcement title
 * @param string $content Announcement content
 * @param string $visibility 'all' or 'members'
 * @return bool Success status
 */
function postAnnouncement($clubid, $title, $content, $visibility) {
    global $conn;

    // Get club name
    $stmt = $conn->prepare("SELECT name FROM clubs WHERE id = ?");
    $stmt->bind_param("i", $clubid);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();
    $stmt->close();

    if (!$club) {
        return false;
    }
    $clubName = $club['name'];

    $message = "New announcement from club '$clubName': $title - $content";
    $type = 'club_announcement';

    if ($visibility === 'all') {
        // Notify all active users
        $stmt_users = $conn->prepare("SELECT id FROM users WHERE is_active = 1");
    } else {
        // Notify club members only
        $stmt_users = $conn->prepare(
            "SELECT userid AS id FROM clubmembers WHERE clubid = ?"
        );
        $stmt_users->bind_param("i", $clubid);
    }
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();

    $success = true;
    while ($user = $result_users->fetch_assoc()) {
        $userid = $user['id'];
        $stmt_notif = $conn->prepare(
            "INSERT INTO notifications (userid, message, type, is_read, created_at) 
             VALUES (?, ?, ?, 0, NOW())"
        );
        $stmt_notif->bind_param("iss", $userid, $message, $type);
        if (!$stmt_notif->execute()) {
            $success = false;
        }
        $stmt_notif->close();
    }
    $stmt_users->close();

    return $success;
}

// Example usage (e.g., from a form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $clubid = (int)$_POST['clubid'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $visibility = $_POST['visibility']; // 'all' or 'members'

    if (postAnnouncement($clubid, $title, $content, $visibility)) {
        echo "Announcement posted and users notified!";
    } else {
        echo "Error posting announcement.";
    }
}
?>