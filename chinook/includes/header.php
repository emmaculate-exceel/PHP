<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : ''; ?>Chinook Album Manager</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <div class="brand">
            <span class="brand-icon">♬</span>
            <span class="brand-name">Chinook <em>Album Manager</em></span>
        </div>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                All Albums
            </a>
            <a href="create.php" class="nav-link nav-link--cta <?php echo basename($_SERVER['PHP_SELF']) === 'create.php' ? 'active' : ''; ?>">
                + New Album
            </a>
        </nav>
    </div>
</header>

<main class="main-content">
