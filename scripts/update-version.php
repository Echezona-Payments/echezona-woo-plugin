<?php

/**
 * Version Update Script
 * 
 * This script updates the version number in the plugin files based on commit type.
 * Usage: php update-version.php [major|minor|patch]
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

if ($argc !== 2 || !in_array($argv[1], ['major', 'minor', 'patch'])) {
    die("Usage: php update-version.php [major|minor|patch]\n");
}

$type = $argv[1];
$plugin_file = __DIR__ . '/../echezona-payments.php';

if (!file_exists($plugin_file)) {
    die("Plugin file not found: $plugin_file\n");
}

// Read the plugin file
$content = file_get_contents($plugin_file);

// Extract current version
preg_match("/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/", $content, $matches);
if (empty($matches[1])) {
    die("Could not find version number in plugin file.\n");
}

$current_version = $matches[1];
$version_parts = explode('.', $current_version);

// Update version based on type
switch ($type) {
    case 'major':
        $version_parts[0]++;
        $version_parts[1] = 0;
        $version_parts[2] = 0;
        break;
    case 'minor':
        $version_parts[1]++;
        $version_parts[2] = 0;
        break;
    case 'patch':
        $version_parts[2]++;
        break;
}

$new_version = implode('.', $version_parts);

// Update version in plugin file
$content = preg_replace(
    "/Version:\s*[0-9]+\.[0-9]+\.[0-9]+/",
    "Version: $new_version",
    $content
);

$content = preg_replace(
    "/define\(\s*'ECZP_VERSION',\s*'[0-9]+\.[0-9]+\.[0-9]+'\s*\);/",
    "define('ECZP_VERSION', '$new_version');",
    $content
);

// Write changes back to file
if (file_put_contents($plugin_file, $content)) {
    echo "Version updated from $current_version to $new_version\n";

    // Update changelog
    $changelog_file = __DIR__ . '/../CHANGELOG.md';
    if (file_exists($changelog_file)) {
        $changelog = file_get_contents($changelog_file);
        $date = date('Y-m-d');
        $new_entry = "\n## [$new_version] - $date\n### Added\n- Automatic version update\n\n";

        // Insert new version entry after the first heading
        $changelog = preg_replace(
            "/# Changelog\n\n/",
            "# Changelog\n\n$new_entry",
            $changelog
        );

        file_put_contents($changelog_file, $changelog);
        echo "Changelog updated\n";
    }
} else {
    die("Failed to update version in plugin file.\n");
}
