#!/usr/bin/env bash
set -euo pipefail

OLD_PLUGIN="person-directory/person-directory.php"
NEW_PLUGIN="nrfc-person-directory/nrfc-person-directory.php"
DRY_RUN="${1:-}"

if [[ "$DRY_RUN" != "" && "$DRY_RUN" != "--dry-run" ]]; then
    echo "Usage: $0 [--dry-run]"
    exit 1
fi

if [[ "$DRY_RUN" == "--dry-run" ]]; then
    echo "Running dry-run migration for plugin basename rename:"
    echo "  $OLD_PLUGIN -> $NEW_PLUGIN"
    DRY_RUN_BOOL="true"
else
    DRY_RUN_BOOL="false"
fi

docker compose exec -T \
    -e NRFC_MIGRATION_OLD="$OLD_PLUGIN" \
    -e NRFC_MIGRATION_NEW="$NEW_PLUGIN" \
    -e NRFC_MIGRATION_DRY_RUN="$DRY_RUN_BOOL" \
    nrfc-wp-dev-web \
    wp eval '
$old = (string) getenv("NRFC_MIGRATION_OLD");
$new = (string) getenv("NRFC_MIGRATION_NEW");
$dry_run = "true" === getenv("NRFC_MIGRATION_DRY_RUN");

$results = [
    "active_plugins" => false,
    "recently_activated" => false,
    "active_sitewide_plugins" => false,
    "update_plugins_transient" => false,
];

$active = get_option("active_plugins", []);
if (is_array($active)) {
    $index = array_search($old, $active, true);
    if (false !== $index) {
        $active[$index] = $new;
        $active = array_values(array_unique($active));
        $results["active_plugins"] = true;
        if (!$dry_run) {
            update_option("active_plugins", $active, true);
        }
    }
}

$recent = get_option("recently_activated", []);
if (is_array($recent) && array_key_exists($old, $recent)) {
    $recent[$new] = $recent[$old];
    unset($recent[$old]);
    $results["recently_activated"] = true;
    if (!$dry_run) {
        update_option("recently_activated", $recent, false);
    }
}

$sitewide = get_site_option("active_sitewide_plugins", []);
if (is_array($sitewide) && array_key_exists($old, $sitewide)) {
    $sitewide[$new] = $sitewide[$old];
    unset($sitewide[$old]);
    $results["active_sitewide_plugins"] = true;
    if (!$dry_run) {
        update_site_option("active_sitewide_plugins", $sitewide);
    }
}

$update_plugins = get_site_transient("update_plugins");
if (is_object($update_plugins)) {
    foreach (["checked", "response", "no_update"] as $property) {
        if (isset($update_plugins->{$property}) && is_array($update_plugins->{$property}) && array_key_exists($old, $update_plugins->{$property})) {
            $update_plugins->{$property}[$new] = $update_plugins->{$property}[$old];
            if (is_object($update_plugins->{$property}[$new])) {
                $update_plugins->{$property}[$new]->plugin = $new;
            }
            unset($update_plugins->{$property}[$old]);
            $results["update_plugins_transient"] = true;
        }
    }

    if ($results["update_plugins_transient"] && !$dry_run) {
        set_site_transient("update_plugins", $update_plugins);
    }
}

echo wp_json_encode([
    "dry_run" => $dry_run,
    "from" => $old,
    "to" => $new,
    "updated" => $results,
], JSON_PRETTY_PRINT) . PHP_EOL;
'


