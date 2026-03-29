<?php
/**
 * flash.php
 * Lightweight session-based flash message helper.
 * Messages are stored for one request only, then discarded.
 */

/**
 * Stores a flash message in the session.
 *
 * @param string $type    Message type: 'success' | 'error' | 'info'
 * @param string $message Human-readable message text
 */
function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieves and clears the current flash message.
 *
 * @return array|null  Associative array with 'type' and 'message', or null
 */
function getFlash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']); // Clear after reading (one-time use)
        return $flash;
    }

    return null;
}

/**
 * Renders a flash message as HTML if one exists.
 * Should be called once per page, near the top of the <main> area.
 */
function renderFlash(): void
{
    $flash = getFlash();
    if ($flash) {
        $type    = htmlspecialchars($flash['type']);
        $message = htmlspecialchars($flash['message']);
        echo "<div class=\"alert alert--{$type}\" role=\"alert\">{$message}</div>";
    }
}
