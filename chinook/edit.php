<?php
/**
 * edit.php
 * Displays a pre-filled form for editing an existing album.
 * On POST, updates the album, its artist, and manages tracks
 * (updates existing, inserts new, deletes removed ones) atomically.
 */

require_once 'includes/db.php';
require_once 'includes/flash.php';

session_start();

// ── Validate the album ID from the query string ───────────────────────────
$albumId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
         ?: filter_input(INPUT_POST, 'album_id', FILTER_VALIDATE_INT);

if (!$albumId || $albumId < 1) {
    header('Location: index.php');
    exit;
}

$errors   = [];
$formData = [];

try {
    $db = getDB();

    // ── Load existing artists for the dropdown ────────────────────────────
    $artists = $db->query("SELECT ArtistId, Name FROM artists ORDER BY Name ASC")->fetchAll();

    // ── Load the album + artist ───────────────────────────────────────────
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
        header('Location: index.php');
        exit;
    }

    // ── Load existing tracks for this album ───────────────────────────────
    $stmtTracks = $db->prepare(
        "SELECT TrackId, Name, Composer, Milliseconds, Bytes, UnitPrice, MediaTypeId, GenreId
         FROM   tracks
         WHERE  AlbumId = :id
         ORDER  BY TrackId ASC"
    );
    $stmtTracks->execute([':id' => $albumId]);
    $existingTracks = $stmtTracks->fetchAll();

} catch (PDOException $e) {
    die('Error loading album: ' . htmlspecialchars($e->getMessage()));
}

$pageTitle = 'Edit: ' . htmlspecialchars($album['AlbumTitle']);

// ── Handle POST submission ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and sanitise
    $formData = [
        'album_id'      => (int) ($_POST['album_id']      ?? 0),
        'album_title'   => trim($_POST['album_title']      ?? ''),
        'artist_choice' => trim($_POST['artist_choice']    ?? ''),
        'artist_id'     => filter_input(INPUT_POST, 'artist_id', FILTER_VALIDATE_INT),
        'artist_name'   => trim($_POST['artist_name']      ?? ''),
        'tracks'        => $_POST['tracks']                 ?? [],
        'delete_tracks' => $_POST['delete_tracks']          ?? [],   // Track IDs to remove
    ];

    // ── Validation ────────────────────────────────────────────────────────
    if ($formData['album_title'] === '') {
        $errors[] = 'Album title is required.';
    }

    if ($formData['artist_choice'] === 'existing') {
        if (!$formData['artist_id'] || $formData['artist_id'] < 1) {
            $errors[] = 'Please select an existing artist.';
        }
    } elseif ($formData['artist_choice'] === 'new') {
        if ($formData['artist_name'] === '') {
            $errors[] = 'New artist name is required.';
        }
    } else {
        $errors[] = 'Please choose an artist option.';
    }

    foreach ($formData['tracks'] as $idx => $track) {
        if (empty(trim($track['name'] ?? ''))) {
            $errors[] = 'Track ' . ($idx + 1) . ': name is required.';
        }
    }

    // ── Persist if valid ──────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Step 1 – Resolve artist
            if ($formData['artist_choice'] === 'new') {
                $stmtNewArtist = $db->prepare("INSERT INTO artists (Name) VALUES (:name)");
                $stmtNewArtist->execute([':name' => $formData['artist_name']]);
                $resolvedArtistId = (int) $db->lastInsertId();
            } else {
                $resolvedArtistId = (int) $formData['artist_id'];
            }

            // Step 2 – Update the album row
            $stmtUpdateAlbum = $db->prepare(
                "UPDATE albums SET Title = :title, ArtistId = :artist_id WHERE AlbumId = :album_id"
            );
            $stmtUpdateAlbum->execute([
                ':title'     => $formData['album_title'],
                ':artist_id' => $resolvedArtistId,
                ':album_id'  => $albumId,
            ]);

            // Step 3 – Delete tracks the user marked for removal
            if (!empty($formData['delete_tracks'])) {
                // Build a safe IN (...) clause using placeholders
                $deleteIds   = array_map('intval', $formData['delete_tracks']);
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $stmtDelete  = $db->prepare(
                    "DELETE FROM tracks WHERE TrackId IN ({$placeholders}) AND AlbumId = ?"
                );
                $stmtDelete->execute([...$deleteIds, $albumId]);
            }

            // Step 4 – Update existing tracks and insert new ones
            $stmtUpdateTrack = $db->prepare(
                "UPDATE tracks
                 SET    Name = :name, Composer = :composer,
                        Milliseconds = :ms, Bytes = :bytes, UnitPrice = :price
                 WHERE  TrackId = :track_id AND AlbumId = :album_id"
            );
            $stmtInsertTrack = $db->prepare(
                "INSERT INTO tracks
                    (Name, AlbumId, MediaTypeId, GenreId, Composer, Milliseconds, Bytes, UnitPrice)
                 VALUES
                    (:name, :album_id, :media_type_id, :genre_id, :composer, :ms, :bytes, :price)"
            );

            foreach ($formData['tracks'] as $track) {
                $trackName = trim($track['name'] ?? '');
                if ($trackName === '') {
                    continue;
                }

                $trackId = filter_var($track['track_id'] ?? null, FILTER_VALIDATE_INT);

                if ($trackId && $trackId > 0) {
                    // Existing track – update in place
                    $stmtUpdateTrack->execute([
                        ':name'     => $trackName,
                        ':composer' => trim($track['composer'] ?? '') ?: null,
                        ':ms'       => max(0, (int)   ($track['milliseconds'] ?? 0)),
                        ':bytes'    => max(0, (int)   ($track['bytes']        ?? 0)),
                        ':price'    => max(0, (float) ($track['price']        ?? 0.99)),
                        ':track_id' => $trackId,
                        ':album_id' => $albumId,
                    ]);
                } else {
                    // New track row added via JS – insert
                    $stmtInsertTrack->execute([
                        ':name'          => $trackName,
                        ':album_id'      => $albumId,
                        ':media_type_id' => 1,
                        ':genre_id'      => null,
                        ':composer'      => trim($track['composer'] ?? '') ?: null,
                        ':ms'            => max(0, (int)   ($track['milliseconds'] ?? 0)),
                        ':bytes'         => max(0, (int)   ($track['bytes']        ?? 0)),
                        ':price'         => max(0, (float) ($track['price']        ?? 0.99)),
                    ]);
                }
            }

            $db->commit();

            setFlash('success', 'Album "' . $formData['album_title'] . '" updated successfully.');
            header('Location: view.php?id=' . $albumId);
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }

    // On error – re-render the form but use submitted values
    // Re-load fresh track list from DB to accurately show deletions that weren't applied
}

// Populate formData defaults from DB record (for initial GET request)
if (empty($formData)) {
    $formData = [
        'album_title'   => $album['AlbumTitle'],
        'artist_choice' => 'existing',
        'artist_id'     => $album['ArtistId'],
        'artist_name'   => '',
    ];
}

require_once 'includes/header.php';
?>

<div class="page-hero">
    <a href="view.php?id=<?php echo (int) $albumId; ?>" class="back-link">&larr; Back to album</a>
    <h1 class="page-title">Edit Album</h1>
    <p class="page-subtitle"><?php echo htmlspecialchars($album['AlbumTitle']); ?></p>
</div>

<?php renderFlash(); ?>

<?php if (!empty($errors)): ?>
<div class="alert alert--error" role="alert">
    <strong>Please fix the following:</strong>
    <ul class="error-list">
        <?php foreach ($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="edit.php?id=<?php echo (int) $albumId; ?>" class="album-form" novalidate>
    <!-- Hidden album ID so POST handler can identify the record -->
    <input type="hidden" name="album_id" value="<?php echo (int) $albumId; ?>">

    <!-- ── Album details ────────────────────────────────────────────────── -->
    <fieldset class="form-section">
        <legend class="form-section__title">Album Details</legend>

        <div class="form-group">
            <label for="album_title" class="form-label">Album Title <span class="required">*</span></label>
            <input
                type="text"
                id="album_title"
                name="album_title"
                class="form-input"
                value="<?php echo htmlspecialchars($formData['album_title']); ?>"
                required
                maxlength="255"
            >
        </div>
    </fieldset>

    <!-- ── Artist ───────────────────────────────────────────────────────── -->
    <fieldset class="form-section">
        <legend class="form-section__title">Artist</legend>

        <div class="form-group">
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="artist_choice" value="existing"
                        <?php echo ($formData['artist_choice'] ?? 'existing') === 'existing' ? 'checked' : ''; ?>
                        id="artist-existing">
                    Use an existing artist
                </label>
                <label class="radio-label">
                    <input type="radio" name="artist_choice" value="new"
                        <?php echo ($formData['artist_choice'] ?? '') === 'new' ? 'checked' : ''; ?>
                        id="artist-new">
                    Add a new artist
                </label>
            </div>
        </div>

        <div class="form-group js-existing-artist">
            <label for="artist_id" class="form-label">Select Artist</label>
            <select id="artist_id" name="artist_id" class="form-select">
                <option value="">— Choose an artist —</option>
                <?php foreach ($artists as $artist): ?>
                    <option value="<?php echo (int) $artist['ArtistId']; ?>"
                        <?php echo ((int) $formData['artist_id']) === (int) $artist['ArtistId'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($artist['Name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group js-new-artist" style="display:none;">
            <label for="artist_name" class="form-label">New Artist Name <span class="required">*</span></label>
            <input
                type="text"
                id="artist_name"
                name="artist_name"
                class="form-input"
                value="<?php echo htmlspecialchars($formData['artist_name'] ?? ''); ?>"
                maxlength="120"
            >
        </div>
    </fieldset>

    <!-- ── Tracks ────────────────────────────────────────────────────────── -->
    <fieldset class="form-section">
        <legend class="form-section__title">Tracks</legend>

        <div id="tracks-container">
        <?php foreach ($existingTracks as $idx => $track): ?>
            <div class="track-row" data-index="<?php echo $idx; ?>">
                <div class="track-row__header">
                    <span class="track-row__label">Track <?php echo $idx + 1; ?></span>
                    <label class="delete-track-label">
                        <input type="checkbox" name="delete_tracks[]"
                               value="<?php echo (int) $track['TrackId']; ?>"
                               class="js-delete-track-cb">
                        Mark for deletion
                    </label>
                </div>
                <!-- Hidden track ID so the handler knows this is an existing row -->
                <input type="hidden" name="tracks[<?php echo $idx; ?>][track_id]"
                       value="<?php echo (int) $track['TrackId']; ?>">
                <div class="track-row__fields">
                    <div class="form-group">
                        <label class="form-label">Track Name <span class="required">*</span></label>
                        <input type="text" name="tracks[<?php echo $idx; ?>][name]" class="form-input"
                               value="<?php echo htmlspecialchars($track['Name']); ?>" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Composer</label>
                        <input type="text" name="tracks[<?php echo $idx; ?>][composer]" class="form-input"
                               value="<?php echo htmlspecialchars($track['Composer'] ?? ''); ?>" maxlength="220">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Duration (ms)</label>
                            <input type="number" name="tracks[<?php echo $idx; ?>][milliseconds]" class="form-input"
                                   value="<?php echo (int) $track['Milliseconds']; ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">File Size (bytes)</label>
                            <input type="number" name="tracks[<?php echo $idx; ?>][bytes]" class="form-input"
                                   value="<?php echo (int) $track['Bytes']; ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price ($)</label>
                            <input type="number" name="tracks[<?php echo $idx; ?>][price]" class="form-input"
                                   value="<?php echo number_format((float) $track['UnitPrice'], 2); ?>"
                                   min="0" step="0.01">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn--ghost btn--block" id="add-track-btn"
                data-start-index="<?php echo count($existingTracks); ?>">
            + Add New Track
        </button>
    </fieldset>

    <div class="form-actions">
        <a href="view.php?id=<?php echo (int) $albumId; ?>" class="btn btn--ghost">Cancel</a>
        <button type="submit" class="btn btn--primary">Save Changes</button>
    </div>
</form>

<!-- Hidden template for new track rows added client-side -->
<template id="track-template">
    <div class="track-row track-row--new" data-index="__IDX__">
        <div class="track-row__header">
            <span class="track-row__label">New Track __NUM__</span>
            <button type="button" class="btn btn--sm btn--ghost js-remove-track">Remove</button>
        </div>
        <div class="track-row__fields">
            <div class="form-group">
                <label class="form-label">Track Name <span class="required">*</span></label>
                <input type="text" name="tracks[__IDX__][name]" class="form-input" placeholder="Track name" maxlength="200">
            </div>
            <div class="form-group">
                <label class="form-label">Composer</label>
                <input type="text" name="tracks[__IDX__][composer]" class="form-input" placeholder="Composer" maxlength="220">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Duration (ms)</label>
                    <input type="number" name="tracks[__IDX__][milliseconds]" class="form-input" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">File Size (bytes)</label>
                    <input type="number" name="tracks[__IDX__][bytes]" class="form-input" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Price ($)</label>
                    <input type="number" name="tracks[__IDX__][price]" class="form-input" value="0.99" min="0" step="0.01">
                </div>
            </div>
        </div>
    </div>
</template>

<?php require_once 'includes/footer.php'; ?>
