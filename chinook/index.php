<?php
/**
 * index.php
 * Displays all albums with their associated artist name and track count.
 * Employees can search/filter albums and navigate to edit or delete actions.
 */

require_once 'includes/db.php';
require_once 'includes/flash.php';

session_start();

$pageTitle = 'All Albums';

// ── Search / filter ────────────────────────────────────────────────────────
// Sanitise the incoming search term to prevent XSS
$search = trim($_GET['search'] ?? '');

// ── Fetch albums with artist name and track count ─────────────────────────
try {
    $db = getDB();

    if ($search !== '') {
        /*
         * When a search term is provided, filter albums by album title
         * OR by the associated artist name — a LIKE wildcard search on both.
         */
        $stmt = $db->prepare(
            "SELECT
                al.AlbumId,
                al.Title         AS AlbumTitle,
                ar.ArtistId,
                ar.Name          AS ArtistName,
                COUNT(t.TrackId) AS TrackCount
             FROM   albums  al
             JOIN   artists ar ON ar.ArtistId = al.ArtistId
             LEFT JOIN tracks t  ON t.AlbumId  = al.AlbumId
             WHERE  al.Title LIKE :search
                OR  ar.Name  LIKE :search
             GROUP  BY al.AlbumId, al.Title, ar.ArtistId, ar.Name
             ORDER  BY ar.Name, al.Title"
        );
        $stmt->execute([':search' => '%' . $search . '%']);
    } else {
        // No search term — return every album
        $stmt = $db->query(
            "SELECT
                al.AlbumId,
                al.Title         AS AlbumTitle,
                ar.ArtistId,
                ar.Name          AS ArtistName,
                COUNT(t.TrackId) AS TrackCount
             FROM   albums  al
             JOIN   artists ar ON ar.ArtistId = al.ArtistId
             LEFT JOIN tracks t  ON t.AlbumId  = al.AlbumId
             GROUP  BY al.AlbumId, al.Title, ar.ArtistId, ar.Name
             ORDER  BY ar.Name, al.Title"
        );
    }

    $albums = $stmt->fetchAll();

} catch (PDOException $e) {
    // Graceful error — show message without exposing query internals
    $albums    = [];
    $dbError   = 'Could not load albums: ' . htmlspecialchars($e->getMessage());
}

require_once 'includes/header.php';
?>

<div class="page-hero">
    <h1 class="page-title">Album Library</h1>
    <p class="page-subtitle"><?php echo count($albums); ?> album<?php echo count($albums) !== 1 ? 's' : ''; ?> in the Chinook catalogue</p>
</div>

<?php renderFlash(); ?>

<?php if (isset($dbError)): ?>
    <div class="alert alert--error"><?php echo $dbError; ?></div>
<?php endif; ?>

<!-- ── Search bar ──────────────────────────────────────────────────────── -->
<form class="search-form" method="get" action="index.php" role="search">
    <label for="search" class="sr-only">Search albums or artists</label>
    <input
        type="search"
        id="search"
        name="search"
        class="search-input"
        placeholder="Search by album title or artist…"
        value="<?php echo htmlspecialchars($search); ?>"
        aria-label="Search albums"
    >
    <button type="submit" class="btn btn--primary">Search</button>
    <?php if ($search !== ''): ?>
        <a href="index.php" class="btn btn--ghost">Clear</a>
    <?php endif; ?>
</form>

<!-- ── Albums table ────────────────────────────────────────────────────── -->
<?php if (empty($albums)): ?>
    <p class="empty-state">No albums found<?php echo $search !== '' ? ' matching "<strong>' . htmlspecialchars($search) . '</strong>"' : ''; ?>.</p>
<?php else: ?>
<div class="table-wrapper">
    <table class="data-table" id="albums-table">
        <thead>
            <tr>
                <th scope="col" class="col-id">#</th>
                <th scope="col" class="col-title">Album Title</th>
                <th scope="col" class="col-artist">Artist</th>
                <th scope="col" class="col-tracks">Tracks</th>
                <th scope="col" class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($albums as $album): ?>
            <tr class="album-row" data-album-id="<?php echo (int) $album['AlbumId']; ?>">
                <td class="col-id"><?php echo (int) $album['AlbumId']; ?></td>
                <td class="col-title">
                    <strong><?php echo htmlspecialchars($album['AlbumTitle']); ?></strong>
                </td>
                <td class="col-artist"><?php echo htmlspecialchars($album['ArtistName']); ?></td>
                <td class="col-tracks">
                    <span class="badge"><?php echo (int) $album['TrackCount']; ?></span>
                </td>
                <td class="col-actions">
                    <a href="view.php?id=<?php echo (int) $album['AlbumId']; ?>"
                       class="btn btn--sm btn--ghost" title="View album details">View</a>
                    <a href="edit.php?id=<?php echo (int) $album['AlbumId']; ?>"
                       class="btn btn--sm btn--secondary" title="Edit album">Edit</a>
                    <button
                        type="button"
                        class="btn btn--sm btn--danger js-delete-btn"
                        data-album-id="<?php echo (int) $album['AlbumId']; ?>"
                        data-album-title="<?php echo htmlspecialchars($album['AlbumTitle'], ENT_QUOTES); ?>"
                        title="Delete album">
                        Delete
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ── Delete confirmation modal ──────────────────────────────────────── -->
<div class="modal-backdrop" id="delete-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" hidden>
    <div class="modal">
        <h2 class="modal-title" id="modal-title">Delete Album</h2>
        <p class="modal-body">
            Are you sure you want to delete <strong id="modal-album-name"></strong>?<br>
            <em>This will also permanently delete all associated tracks.</em>
        </p>
        <div class="modal-actions">
            <button type="button" class="btn btn--ghost" id="modal-cancel">Cancel</button>
            <form method="post" action="delete.php" style="display:inline;">
                <input type="hidden" name="album_id" id="modal-album-id">
                <button type="submit" class="btn btn--danger">Yes, Delete</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
