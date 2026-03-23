<?php
/**
 * Clean up on uninstall.
 *
 * @package GF_Require_If
 * @license AGPL-3.0-or-later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove cached GitHub release data.
delete_transient( 'grfi_github_release' );
