<?php
/**
 * delete.php
 * Handles deletion of an album and all its associated tracks.
 * Uses a transaction to ensure both the tracks and album are removed atomically.
 * Accepts only POST requests for CSRF safety — never GET.
 */

require_once 'includes/db.php';
require_once 'includes/flash.php';

session_start();

// ── Only allow POST requests ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ── Validate the submitted album ID ──────────────────────────────────────
$albumId = filter_input(INPUT_POST, 'album_id', FILTER_VALIDATE_INT);

if (!$albumId || $albumId < 1) {
    setFlash('error', 'Invalid album ID — nothing was deleted.');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();

    // Retrieve the album title before deleting so we can show it in the flash message
    $stmtTitle = $db->prepare("SELECT Title FROM albums WHERE AlbumId = :id LIMIT 1");
    $stmtTitle->execute([':id' => $albumId]);
    $album = $stmtTitle->fetch();

    if (!$album) {
        setFlash('error', 'Album not found — it may have already been deleted.');
        header('Location: index.php');
        exit;
    }

    $db->beginTransaction();

    // Step 1 – Delete all tracks belonging to this album first (foreign key constraint)
    $stmtTracks = $db->prepare("DELETE FROM tracks WHERE AlbumId = :id");
    $stmtTracks->execute([':id' => $albumId]);
    $tracksDeleted = $stmtTracks->rowCount();

    // Step 2 – Delete the album record itself
    $stmtAlbum = $db->prepare("DELETE FROM albums WHERE AlbumId = :id");
    $stmtAlbum->execute([':id' => $albumId]);

    $db->commit();

    // Build a user-friendly confirmation message
    $trackWord = $tracksDeleted === 1 ? 'track' : 'tracks';
    setFlash(
        'success',
        "Album \"{$album['Title']}\" and {$tracksDeleted} associated {$trackWord} were permanently deleted."
    );

} catch (PDOException $e) {
    // Roll back and notify the user if anything went wrong
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    setFlash('error', 'Deletion failed: ' . htmlspecialchars($e->getMessage()));
}

// Always redirect back to the album list after deletion
header('Location: index.php');
exit;
