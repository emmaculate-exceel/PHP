<?php
/**
 * view.php
 * Displays the full details of a single album, including its artist
 * and a complete listing of all associated tracks.
 */

require_once 'includes/db.php';
require_once 'includes/flash.php';

session_start();

// ── Validate the incoming album ID ────────────────────────────────────────
$albumId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$albumId || $albumId < 1) {
    // Invalid or missing ID — redirect to the album list
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();

    // ── Fetch the album and its artist ────────────────────────────────────
    $stmtAlbum = $db->prepare(
        "SELECT al.AlbumId, al.Title AS AlbumTitle, ar.ArtistId, ar.Name AS ArtistName
         FROM   albums  al
         JOIN   artists ar ON ar.ArtistId = al.ArtistId
         WHERE  al.AlbumId = :id
         LIMIT  1"
    );
    $stmtAlbum->execute([':id' => $albumId]);
    $album = $stmtAlbum->fetch();

    if (!$album) {
        // Album not found in the database — return to list
        header('Location: index.php');
        exit;
    }

    // ── Fetch all tracks for this album ───────────────────────────────────
    $stmtTracks = $db->prepare(
        "SELECT TrackId, Name, Composer, Milliseconds, Bytes, UnitPrice
         FROM   tracks
         WHERE  AlbumId = :id
         ORDER  BY TrackId ASC"
    );
    $stmtTracks->execute([':id' => $albumId]);
    $tracks = $stmtTracks->fetchAll();

} catch (PDOException $e) {
    die('Error loading album: ' . htmlspecialchars($e->getMessage()));
}

$pageTitle = htmlspecialchars($album['AlbumTitle']);

/**
 * Converts a duration in milliseconds to a human-readable MM:SS string.
 *
 * @param int $ms Duration in milliseconds
 * @return string  Formatted as "M:SS"
 */
function formatDuration(int $ms): string
{
    $totalSeconds = (int) round($ms / 1000);
    $minutes      = intdiv($totalSeconds, 60);
    $seconds      = $totalSeconds % 60;
    return sprintf('%d:%02d', $minutes, $seconds);
}

require_once 'includes/header.php';
?>

<div class="page-hero">
    <a href="index.php" class="back-link">&larr; Back to all albums</a>
    <h1 class="page-title"><?php echo htmlspecialchars($album['AlbumTitle']); ?></h1>
    <p class="page-subtitle">by <strong><?php echo htmlspecialchars($album['ArtistName']); ?></strong>
        &mdash; <?php echo count($tracks); ?> track<?php echo count($tracks) !== 1 ? 's' : ''; ?>
    </p>
</div>

<?php renderFlash(); ?>

<div class="album-meta-bar">
    <span class="meta-item"><span class="meta-label">Album ID</span> <?php echo (int) $album['AlbumId']; ?></span>
    <span class="meta-item"><span class="meta-label">Artist ID</span> <?php echo (int) $album['ArtistId']; ?></span>
    <div class="meta-actions">
        <a href="edit.php?id=<?php echo (int) $album['AlbumId']; ?>" class="btn btn--secondary">Edit Album</a>
    </div>
</div>

<!-- ── Track listing ───────────────────────────────────────────────────── -->
<h2 class="section-heading">Track Listing</h2>

<?php if (empty($tracks)): ?>
    <p class="empty-state">This album has no tracks recorded yet.</p>
<?php else: ?>
<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col" class="col-id">#</th>
                <th scope="col">Track Name</th>
                <th scope="col">Composer</th>
                <th scope="col">Duration</th>
                <th scope="col">File Size</th>
                <th scope="col">Price</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tracks as $i => $track): ?>
            <tr>
                <td class="col-id"><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($track['Name']); ?></strong></td>
                <td><?php echo htmlspecialchars($track['Composer'] ?? '—'); ?></td>
                <td><?php echo formatDuration((int) $track['Milliseconds']); ?></td>
                <td><?php echo number_format((int) $track['Bytes'] / (1024 * 1024), 2); ?> MB</td>
                <td>$<?php echo number_format((float) $track['UnitPrice'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
