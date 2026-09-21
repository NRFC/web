<?php
/**
 * Plugin Name: NRFC Match Reports
 * Description: Adds a "Match Report" custom post type linked to Fixtures.
 * Version: 1.0.0
 * Author: NRFC Automation
 * License: GPL2+
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('NRFCMatchReports\\MatchReports')) {
    require_once __DIR__ . '/src/MatchReports.php';
}

use NRFCMatchReports\MatchReports;

// Bootstrap the plugin
$match_reports_plugin = new MatchReports();

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array($match_reports_plugin, 'activate'));
register_deactivation_hook(__FILE__, array($match_reports_plugin, 'deactivate'));
