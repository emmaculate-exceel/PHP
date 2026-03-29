-- ============================================================
-- chinook_setup.sql
-- Minimal schema to support the Chinook Album Manager app.
-- Run this ONLY if you do not already have the Chinook database.
-- The official Chinook dataset is available at:
-- https://github.com/lerocha/chinook-database
-- ============================================================

-- Create the database (skip if it already exists)
CREATE DATABASE IF NOT EXISTS chinook
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE chinook;

-- ── Artists ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS artists (
    ArtistId INT          NOT NULL AUTO_INCREMENT,
    Name     VARCHAR(120),
    PRIMARY KEY (ArtistId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Albums ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS albums (
    AlbumId  INT          NOT NULL AUTO_INCREMENT,
    Title    VARCHAR(160) NOT NULL,
    ArtistId INT          NOT NULL,
    PRIMARY KEY (AlbumId),
    CONSTRAINT fk_album_artist FOREIGN KEY (ArtistId) REFERENCES artists (ArtistId)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Media Types ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS media_types (
    MediaTypeId INT         NOT NULL AUTO_INCREMENT,
    Name        VARCHAR(120),
    PRIMARY KEY (MediaTypeId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert a default media type so new tracks have a valid FK
INSERT IGNORE INTO media_types (MediaTypeId, Name) VALUES (1, 'MPEG audio file');

-- ── Genres ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS genres (
    GenreId INT         NOT NULL AUTO_INCREMENT,
    Name    VARCHAR(120),
    PRIMARY KEY (GenreId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tracks ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tracks (
    TrackId     INT            NOT NULL AUTO_INCREMENT,
    Name        VARCHAR(200)   NOT NULL,
    AlbumId     INT,
    MediaTypeId INT            NOT NULL,
    GenreId     INT,
    Composer    VARCHAR(220),
    Milliseconds INT           NOT NULL,
    Bytes       INT,
    UnitPrice   NUMERIC(10, 2) NOT NULL,
    PRIMARY KEY (TrackId),
    CONSTRAINT fk_track_album      FOREIGN KEY (AlbumId)      REFERENCES albums      (AlbumId)      ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_track_mediatype  FOREIGN KEY (MediaTypeId)  REFERENCES media_types (MediaTypeId)  ON DELETE NO ACTION ON UPDATE CASCADE,
    CONSTRAINT fk_track_genre      FOREIGN KEY (GenreId)      REFERENCES genres      (GenreId)      ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Sample data (optional — remove if using the full Chinook dataset) ────
INSERT IGNORE INTO artists (ArtistId, Name) VALUES
    (1, 'AC/DC'),
    (2, 'Accept'),
    (3, 'Aerosmith');

INSERT IGNORE INTO albums (AlbumId, Title, ArtistId) VALUES
    (1, 'For Those About To Rock We Salute You', 1),
    (2, 'Balls to the Wall', 2),
    (3, 'Toys in the Attic', 3);

INSERT IGNORE INTO tracks (TrackId, Name, AlbumId, MediaTypeId, Composer, Milliseconds, Bytes, UnitPrice) VALUES
    (1, 'For Those About to Rock (We Salute You)', 1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 343719, 11170334, 0.99),
    (2, 'Put The Finger On You',                  1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 205662,  6713451, 0.99),
    (3, 'Balls to the Wall',                       2, 1, NULL,                                        342562, 10887369, 0.99),
    (4, 'Toys in the Attic',                       3, 1, 'Steven Tyler, Joe Perry, Brad Whitford',   252051,  8426975, 0.99);
