<?php
include_once("../connect.php");

/**
 * Send club application notifications
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $clubName Club name
 */
function notifyClubApplicationSubmitted($conn, $userId, $clubName) {
    $message = "Votre demande d'adhésion au club '$clubName' a été soumise et est en attente d'approbation.";
    createNotification($conn, $userId, $message, "CLUB");
}

/**
 * Notify user about club application decision
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $clubName Club name
 * @param bool $accepted Whether application was accepted
 */
function notifyClubApplicationDecision($conn, $userId, $clubName, $accepted) {
    if ($accepted) {
        $message = "Félicitations! Votre demande d'adhésion au club '$clubName' a été approuvée.";
    } else {
        $message = "Votre demande d'adhésion au club '$clubName' n'a pas été retenue.";
    }
    createNotification($conn, $userId, $message, "CLUB");
}

/**
 * Notify user about role assignment in a club
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $clubName Club name
 * @param string $role The assigned role
 */
function notifyRoleAssignment($conn, $userId, $clubName, $role) {
    $message = "Vous avez été désigné comme '$role' dans le club '$clubName'.";
    createNotification($conn, $userId, $message, "CLUB");
}

/**
 * Send notifications for a club update/announcement
 * @param mysqli $conn Database connection
 * @param int $clubId Club ID
 * @param string $updateTitle Update/announcement title
 */
function notifyClubUpdate($conn, $clubId, $updateTitle) {
    // Get all members of the club
    $query = "SELECT userid FROM clubmembers WHERE clubid = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $clubId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get club name
    $clubQuery = "SELECT name FROM clubs WHERE id = ?";
    $clubStmt = $conn->prepare($clubQuery);
    $clubStmt->bind_param("i", $clubId);
    $clubStmt->execute();
    $clubResult = $clubStmt->get_result();
    $club = $clubResult->fetch_assoc();
    
    // Send notification to each member
    while ($member = $result->fetch_assoc()) {
        $message = "Nouvelle annonce du club '{$club['name']}': $updateTitle";
        createNotification($conn, $member['userid'], $message, "CLUB");
    }
}

/**
 * Send notification when a new event is created
 * @param mysqli $conn Database connection
 * @param int $clubId Club ID
 * @param string $eventName Event name
 * @param string $eventDate Event date (formatted)
 */
function notifyNewEvent($conn, $clubId, $eventName, $eventDate) {
    // Get all members of the club
    $query = "SELECT userid FROM clubmembers WHERE clubid = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $clubId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get club name
    $clubQuery = "SELECT name FROM clubs WHERE id = ?";
    $clubStmt = $conn->prepare($clubQuery);
    $clubStmt->bind_param("i", $clubId);
    $clubStmt->execute();
    $clubResult = $clubStmt->get_result();
    $club = $clubResult->fetch_assoc();
    
    // Send notification to each member
    while ($member = $result->fetch_assoc()) {
        $message = "Nouvel événement: '{$eventName}' organisé par '{$club['name']}' le {$eventDate}";
        createNotification($conn, $member['userid'], $message, "EVENT");
    }
}

/**
 * Notify user about event registration status
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $eventName Event name
 * @param bool $accepted Whether registration was accepted
 */
function notifyEventRegistration($conn, $userId, $eventName, $accepted) {
    if ($accepted) {
        $message = "Votre inscription à l'événement '$eventName' a été confirmée.";
    } else {
        $message = "Votre inscription à l'événement '$eventName' n'a pas pu être confirmée.";
    }
    createNotification($conn, $userId, $message, "EVENT");
}

/**
 * Send system-wide notification to all users
 * @param mysqli $conn Database connection
 * @param string $message The notification message
 */
function notifyAllUsers($conn, $message) {
    $query = "SELECT id FROM users WHERE is_active = 1";
    $result = $conn->query($query);
    
    while ($user = $result->fetch_assoc()) {
        createNotification($conn, $user['id'], $message, "SYSTEM");
    }
}
?>