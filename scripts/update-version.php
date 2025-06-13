<?php

/**
 * Version Update Script
 * 
 * This script updates the version number in the plugin files based on commit type.
 * Usage: php update-version.php [major|minor|patch]
 */

if (php_sapi_name() !== 'cli') {
    die(esc_html(
        /* translators: Error message shown when script is not run from command line */
        __('This script can only be run from the command line.', 'echezona-woo-payments')
    ));
}

if ($argc !== 2 || !in_array($argv[1], ['major', 'minor', 'patch'])) {
    die(esc_html(sprintf(
        /* translators: Command line usage instructions for the version update script. %s represents the command name */
        __('Usage: %s [major|minor|patch]', 'echezona-woo-payments'),
        'php update-version.php'
    )) . "\n");
}

$type = $argv[1];
$plugin_file = __DIR__ . '/../echezona-payments.php';

if (!file_exists($plugin_file)) {
    die(esc_html(sprintf(
        /* translators: %s: The full path to the plugin file that could not be found */
        __('Plugin file not found: %s', 'echezona-woo-payments'),
        $plugin_file
    )) . "\n");
}

// Read the plugin file
$content = file_get_contents($plugin_file);

// Extract current version
preg_match("/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/", $content, $matches);
if (empty($matches[1])) {
    die(esc_html(
        /* translators: Error message shown when version number cannot be found in plugin file */
        __('Could not find version number in plugin file.', 'echezona-woo-payments')
        ) . "\n");
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
    "Version: " . esc_sql($new_version),
    $content
);

$content = preg_replace(
    "/define\(\s*'ECZP_VERSION',\s*'[0-9]+\.[0-9]+\.[0-9]+'\s*\);/",
    "define('ECZP_VERSION', '" . esc_sql($new_version) . "');",
    $content
);

// Write changes back to file
if (file_put_contents($plugin_file, $content)) {
    echo esc_html(sprintf(
        /* translators: 1: The current version number before update, 2: The new version number after update */
        __('Version updated from %1$s to %2$s', 'echezona-woo-payments'),
        $current_version,
        $new_version
    )) . "\n";

    // Update changelog
    $changelog_file = __DIR__ . '/../CHANGELOG.md';
    if (file_exists($changelog_file)) {
        $changelog = file_get_contents($changelog_file);
        $date = gmdate('Y-m-d');
        /* translators: 1: The new version number, 2: The current date in YYYY-MM-DD format */
        $new_entry = sprintf(
            "\n## [%1$s] - %2$s\n### Added\n- " . esc_html(__('Automatic version update', 'echezona-woo-payments')) . "\n\n",
            esc_sql($new_version),
            esc_sql($date)
        );

        // Insert new version entry after the first heading
        $changelog = preg_replace(
            "/# Changelog\n\n/",
            "# Changelog\n\n" . $new_entry,
            $changelog
        );

        file_put_contents($changelog_file, $changelog);
        echo esc_html(
            /* translators: Success message shown when changelog is updated */
            __('Changelog updated', 'echezona-woo-payments')
            ) . "\n";
    }
} else {
    die(esc_html(
        /* translators: Error message shown when version update fails */
        __('Failed to update version in plugin file.', 'echezona-woo-payments')
        ) . "\n");
}
