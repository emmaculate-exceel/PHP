<?php
/**
 * create.php
 * Handles both GET (display the blank form) and POST (process form submission)
 * for inserting a new album, optionally with a new or existing artist, and
 * one or more tracks — all within a single database transaction for integrity.
 */

require_once 'includes/db.php';
require_once 'includes/flash.php';

session_start();

$pageTitle = 'New Album';
$errors    = [];
$formData  = [];   // Re-populate form on validation failure

// ── Fetch existing artists for the dropdown ───────────────────────────────
try {
    $db      = getDB();
    $artists = $db->query("SELECT ArtistId, Name FROM artists ORDER BY Name ASC")->fetchAll();
} catch (PDOException $e) {
    die('Could not load artists: ' . htmlspecialchars($e->getMessage()));
}

// ── Handle POST submission ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and sanitise form input
    $formData = [
        'album_title'   => trim($_POST['album_title']   ?? ''),
        'artist_choice' => trim($_POST['artist_choice'] ?? ''), // 'existing' or 'new'
        'artist_id'     => filter_input(INPUT_POST, 'artist_id',   FILTER_VALIDATE_INT),
        'artist_name'   => trim($_POST['artist_name']   ?? ''),
        'tracks'        => $_POST['tracks']              ?? [],   // Array of track rows
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

    // Validate each submitted track row
    foreach ($formData['tracks'] as $idx => $track) {
        $trackNum = $idx + 1;
        if (empty(trim($track['name'] ?? ''))) {
            $errors[] = "Track {$trackNum}: name is required.";
        }
    }

    // ── Persist if valid ──────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Step 1 – Resolve the artist (create new or use existing)
            if ($formData['artist_choice'] === 'new') {
                $stmtArtist = $db->prepare(
                    "INSERT INTO artists (Name) VALUES (:name)"
                );
                $stmtArtist->execute([':name' => $formData['artist_name']]);
                $artistId = (int) $db->lastInsertId();
            } else {
                $artistId = (int) $formData['artist_id'];
            }

            // Step 2 – Insert the album
            $stmtAlbum = $db->prepare(
                "INSERT INTO albums (Title, ArtistId) VALUES (:title, :artist_id)"
            );
            $stmtAlbum->execute([
                ':title'     => $formData['album_title'],
                ':artist_id' => $artistId,
            ]);
            $newAlbumId = (int) $db->lastInsertId();

            // Step 3 – Insert tracks (if any were provided)
            $stmtTrack = $db->prepare(
                "INSERT INTO tracks
                    (Name, AlbumId, MediaTypeId, GenreId, Composer, Milliseconds, Bytes, UnitPrice)
                 VALUES
                    (:name, :album_id, :media_type_id, :genre_id, :composer, :ms, :bytes, :price)"
            );

            foreach ($formData['tracks'] as $track) {
                $trackName = trim($track['name'] ?? '');
                if ($trackName === '') {
                    continue; // Skip blank rows (already caught above if required)
                }

                $stmtTrack->execute([
                    ':name'          => $trackName,
                    ':album_id'      => $newAlbumId,
                    ':media_type_id' => max(1, (int) ($track['media_type_id'] ?? 1)), // Default: MPEG audio
                    ':genre_id'      => ($track['genre_id'] ?? null) ?: null,
                    ':composer'      => trim($track['composer'] ?? '') ?: null,
                    ':ms'            => max(0, (int)   ($track['milliseconds'] ?? 0)),
                    ':bytes'         => max(0, (int)   ($track['bytes']        ?? 0)),
                    ':price'         => max(0, (float) ($track['price']        ?? 0.99)),
                ]);
            }

            $db->commit();

            // Redirect to the new album's view page with a success message
            setFlash('success', 'Album "' . $formData['album_title'] . '" was created successfully.');
            header('Location: view.php?id=' . $newAlbumId);
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

require_once 'includes/header.php';
?>

<div class="page-hero">
    <a href="index.php" class="back-link">&larr; Back to all albums</a>
    <h1 class="page-title">Add New Album</h1>
    <p class="page-subtitle">Create an album, assign an artist, and add tracks in one go.</p>
</div>

<?php renderFlash(); ?>

<!-- ── Validation errors ───────────────────────────────────────────────── -->
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

<!-- ── Create form ─────────────────────────────────────────────────────── -->
<form method="post" action="create.php" class="album-form" novalidate id="create-form">

    <!-- Section 1: Album details -->
    <fieldset class="form-section">
        <legend class="form-section__title">Album Details</legend>

        <div class="form-group">
            <label for="album_title" class="form-label">Album Title <span class="required">*</span></label>
            <input
                type="text"
                id="album_title"
                name="album_title"
                class="form-input"
                value="<?php echo htmlspecialchars($formData['album_title'] ?? ''); ?>"
                required
                maxlength="255"
                placeholder="e.g. Dark Side of the Moon"
            >
        </div>
    </fieldset>

    <!-- Section 2: Artist -->
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
                        <?php echo ((int) ($formData['artist_id'] ?? 0)) === (int) $artist['ArtistId'] ? 'selected' : ''; ?>>
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
                placeholder="e.g. Pink Floyd"
                maxlength="120"
            >
        </div>
    </fieldset>

    <!-- Section 3: Tracks -->
    <fieldset class="form-section">
        <legend class="form-section__title">Tracks <span class="legend-note">(optional)</span></legend>

        <div id="tracks-container">
            <?php
            // Re-render any previously submitted tracks on error
            $existingTracks = $formData['tracks'] ?? [];
            if (empty($existingTracks)) {
                $existingTracks = [[]]; // At least one blank row
            }
            foreach ($existingTracks as $idx => $t):
            ?>
            <div class="track-row" data-index="<?php echo $idx; ?>">
                <div class="track-row__header">
                    <span class="track-row__label">Track <?php echo $idx + 1; ?></span>
                    <button type="button" class="btn btn--sm btn--ghost js-remove-track">Remove</button>
                </div>
                <div class="track-row__fields">
                    <div class="form-group">
                        <label class="form-label">Track Name <span class="required">*</span></label>
                        <input type="text" name="tracks[<?php echo $idx; ?>][name]" class="form-input"
                               value="<?php echo htmlspecialchars($t['name'] ?? ''); ?>"
                               placeholder="Track name" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Composer</label>
                        <input type="text" name="tracks[<?php echo $idx; ?>][composer]" class="form-input"
                               value="<?php echo htmlspecialchars($t['composer'] ?? ''); ?>"
                               placeholder="Composer name" maxlength="220">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Duration (ms)</label>
                            <input type="number" name="tracks[<?php echo $idx; ?>][milliseconds]" class="form-input"
                                   value="<?php echo (int) ($t['milliseconds'] ?? 0); ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">File Size (bytes)</label>
                            <input type="number" name="tracks[<?php echo $idx; ?>][bytes]" class="form-input"
                                   value="<?php echo (int) ($t['bytes'] ?? 0); ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price ($)</label>
                            <input type="number" name="tracks[<?php echo $idx; ?>][price]" class="form-input"
                                   value="<?php echo number_format((float) ($t['price'] ?? 0.99), 2); ?>"
                                   min="0" step="0.01">
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn--ghost btn--block" id="add-track-btn">+ Add Another Track</button>
    </fieldset>

    <div class="form-actions">
        <a href="index.php" class="btn btn--ghost">Cancel</a>
        <button type="submit" class="btn btn--primary">Create Album</button>
    </div>

</form>

<!-- Hidden template for JS-cloned track rows -->
<template id="track-template">
    <div class="track-row" data-index="__IDX__">
        <div class="track-row__header">
            <span class="track-row__label">Track __NUM__</span>
            <button type="button" class="btn btn--sm btn--ghost js-remove-track">Remove</button>
        </div>
        <div class="track-row__fields">
            <div class="form-group">
                <label class="form-label">Track Name <span class="required">*</span></label>
                <input type="text" name="tracks[__IDX__][name]" class="form-input" placeholder="Track name" maxlength="200">
            </div>
            <div class="form-group">
                <label class="form-label">Composer</label>
                <input type="text" name="tracks[__IDX__][composer]" class="form-input" placeholder="Composer name" maxlength="220">
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
