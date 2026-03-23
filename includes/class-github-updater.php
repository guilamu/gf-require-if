<?php
/**
 * GitHub Auto-Updater for Gravity Forms Require If.
 *
 * Enables automatic updates from GitHub releases.
 *
 * @package GF_Require_If
 * @license AGPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GRFI_GitHub_Updater {

	private const GITHUB_USER         = 'guilamu';
	private const GITHUB_REPO         = 'gravity-forms-require-if';
	private const PLUGIN_FILE         = 'gravity-forms-require-if/gravity-forms-require-if.php';
	private const PLUGIN_SLUG         = 'gravity-forms-require-if';
	private const PLUGIN_NAME         = 'Gravity Forms Require If';
	private const PLUGIN_DESCRIPTION  = 'Make the required state of supported Gravity Forms fields conditional — based on form field values, user roles, URL parameters, and more.';
	private const REQUIRES_WP         = '6.0';
	private const TESTED_WP           = '6.7';
	private const REQUIRES_PHP        = '8.0';
	private const TEXT_DOMAIN         = 'gf-require-if';
	private const CACHE_KEY           = 'grfi_github_release';
	private const CACHE_EXPIRATION    = 43200; // 12 hours.
	private const GITHUB_TOKEN        = '';

	public static function init(): void {
		add_filter( 'update_plugins_github.com', array( self::class, 'check_for_update' ), 10, 4 );
		add_filter( 'plugins_api', array( self::class, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( self::class, 'fix_folder_name' ), 10, 4 );
		add_filter( 'plugin_row_meta', array( self::class, 'plugin_row_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_thickbox' ) );
	}

	private static function get_release_data(): ?array {
		$release_data = get_transient( self::CACHE_KEY );

		if ( false !== $release_data && is_array( $release_data ) ) {
			return $release_data;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO ),
			array(
				'user-agent' => 'WordPress/' . self::PLUGIN_SLUG,
				'timeout'    => 15,
				'headers'    => ! empty( self::GITHUB_TOKEN )
					? array( 'Authorization' => 'token ' . self::GITHUB_TOKEN )
					: array(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$release_data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release_data['tag_name'] ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION );

		return $release_data;
	}

	private static function get_package_url( array $release_data ): string {
		if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
			foreach ( $release_data['assets'] as $asset ) {
				if (
					isset( $asset['browser_download_url'], $asset['name'] ) &&
					str_ends_with( $asset['name'], '.zip' )
				) {
					return $asset['browser_download_url'];
				}
			}
		}

		return $release_data['zipball_url'] ?? '';
	}

	public static function check_for_update( $update, array $plugin_data, string $plugin_file, $locales ) {
		if ( self::PLUGIN_FILE !== $plugin_file ) {
			return $update;
		}

		$release_data = self::get_release_data();
		if ( null === $release_data ) {
			return $update;
		}

		$new_version = ltrim( $release_data['tag_name'], 'v' );

		if ( version_compare( $plugin_data['Version'], $new_version, '>=' ) ) {
			return $update;
		}

		return array(
			'id'            => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'slug'          => self::PLUGIN_SLUG,
			'plugin'        => self::PLUGIN_FILE,
			'new_version'   => $new_version,
			'version'       => $new_version,
			'package'       => self::get_package_url( $release_data ),
			'url'           => $release_data['html_url'],
			'tested'        => self::TESTED_WP,
			'requires_php'  => self::REQUIRES_PHP,
			'compatibility' => new stdClass(),
			'icons'         => array(),
			'banners'       => array(),
		);
	}

	public static function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $res;
		}

		$release_data = self::get_release_data();

		// Parse README.md sections for the modal.
		$readme_sections = self::parse_readme();

		if ( null === $release_data ) {
			$plugin_file = WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE;
			$plugin_data = get_plugin_data( $plugin_file, false, false );

			$res               = new stdClass();
			$res->name         = self::PLUGIN_NAME;
			$res->slug         = self::PLUGIN_SLUG;
			$res->plugin       = self::PLUGIN_FILE;
			$res->version      = $plugin_data['Version'] ?? '1.0.0';
			$res->author       = sprintf( '<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER );
			$res->homepage     = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
			$res->requires     = self::REQUIRES_WP;
			$res->tested       = self::TESTED_WP;
			$res->requires_php = self::REQUIRES_PHP;
			$res->sections     = array_filter( array(
				'description'  => $readme_sections['description'] ?: self::PLUGIN_DESCRIPTION,
				'installation' => $readme_sections['installation'],
				'faq'          => $readme_sections['faq'],
				'changelog'    => $readme_sections['changelog'] ?: sprintf(
					'<p>Unable to fetch changelog from GitHub. Visit <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a>.</p>',
					self::GITHUB_USER,
					self::GITHUB_REPO
				),
			) );
			return $res;
		}

		$new_version = ltrim( $release_data['tag_name'], 'v' );

		$res                = new stdClass();
		$res->name          = self::PLUGIN_NAME;
		$res->slug          = self::PLUGIN_SLUG;
		$res->plugin        = self::PLUGIN_FILE;
		$res->version       = $new_version;
		$res->author        = sprintf( '<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER );
		$res->homepage      = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
		$res->download_link = self::get_package_url( $release_data );
		$res->requires      = self::REQUIRES_WP;
		$res->tested        = self::TESTED_WP;
		$res->requires_php  = self::REQUIRES_PHP;
		$res->last_updated  = $release_data['published_at'] ?? '';
		$res->sections      = array_filter( array(
			'description'  => $readme_sections['description'] ?: self::PLUGIN_DESCRIPTION,
			'installation' => $readme_sections['installation'],
			'faq'          => $readme_sections['faq'],
			'changelog'    => ! empty( $release_data['body'] )
				? nl2br( esc_html( $release_data['body'] ) )
				: ( $readme_sections['changelog'] ?: sprintf(
					'See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.',
					self::GITHUB_USER,
					self::GITHUB_REPO
				) ),
		) );

		return $res;
	}

	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) ) {
			return $source;
		}

		if ( self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
			return $source;
		}

		$correct_folder = dirname( self::PLUGIN_FILE );
		$source_folder  = basename( untrailingslashit( $source ) );

		if ( $source_folder === $correct_folder ) {
			return $source;
		}

		$new_source = trailingslashit( $remote_source ) . $correct_folder . '/';

		if ( $wp_filesystem && $wp_filesystem->move( $source, $new_source ) ) {
			return $new_source;
		}

		if ( $wp_filesystem && $wp_filesystem->copy( $source, $new_source, true ) && $wp_filesystem->delete( $source, true ) ) {
			return $new_source;
		}

		return new WP_Error(
			'rename_failed',
			__( 'Unable to rename the update folder. Please retry or update manually.', 'gf-require-if' )
		);
	}

	/**
	 * Add "View details" link to the plugin row meta.
	 *
	 * @param array  $links Plugin row meta links.
	 * @param string $file  Plugin file path.
	 * @return array Modified links.
	 */
	public static function plugin_row_meta( array $links, string $file ): array {
		if ( self::PLUGIN_FILE !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
			esc_url(
				admin_url(
					'plugin-install.php?tab=plugin-information&plugin=' . self::PLUGIN_SLUG .
					'&TB_iframe=true&width=600&height=550'
				)
			),
			esc_attr( sprintf(
				/* translators: %s: plugin name */
				__( 'More information about %s', 'gf-require-if' ),
				self::PLUGIN_NAME
			) ),
			esc_attr( self::PLUGIN_NAME ),
			esc_html__( 'View details', 'gf-require-if' )
		);

		return $links;
	}

	/**
	 * Enqueue Thickbox on the plugins page for the View Details modal.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_thickbox( string $hook ): void {
		if ( 'plugins.php' === $hook ) {
			add_thickbox();
		}
	}

	// =========================================================================
	// README.md Parsing
	// =========================================================================

	/**
	 * Parse the plugin's README.md and return sections for the modal.
	 *
	 * @return array{description: string, installation: string, changelog: string, faq: string}
	 */
	private static function parse_readme(): array {
		$defaults = array(
			'description'  => '',
			'installation' => '',
			'changelog'    => '',
			'faq'          => '',
		);

		$readme_path = WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN_FILE ) . '/README.md';
		if ( ! file_exists( $readme_path ) ) {
			return $defaults;
		}

		$content = file_get_contents( $readme_path );
		if ( false === $content ) {
			return $defaults;
		}

		$html = self::markdown_to_html( $content );

		return array(
			'description'  => self::extract_section( $html, 'Description' )
			                  ?: self::extract_first_section( $html ),
			'installation' => self::extract_section( $html, 'Installation' ),
			'changelog'    => self::extract_section( $html, 'Changelog' ),
			'faq'          => self::extract_section( $html, 'FAQ' )
			                  ?: self::extract_section( $html, 'Frequently Asked Questions' ),
		);
	}

	/**
	 * Convert Markdown to basic HTML.
	 *
	 * @param string $markdown Raw Markdown content.
	 * @return string HTML.
	 */
	private static function markdown_to_html( string $markdown ): string {
		$html = str_replace( "\r\n", "\n", $markdown );

		// Headers — use h4 for ### to avoid WP core h3 margin styling.
		$html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
		$html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
		$html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );

		// Inline formatting.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
		$html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );
		$html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );
		$html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html );

		// Process lists line by line for proper ul/ol wrapping.
		$lines        = explode( "\n", $html );
		$result_lines = array();
		$in_ol        = false;
		$in_ul        = false;

		foreach ( $lines as $line ) {
			if ( preg_match( '/^(\d+)\. (.+)$/', $line, $m ) ) {
				if ( $in_ul ) {
					$result_lines[] = '</ul>';
					$in_ul = false;
				}
				if ( ! $in_ol ) {
					$result_lines[] = '<ol>';
					$in_ol = true;
				}
				$result_lines[] = '<li>' . $m[2] . '</li>';
			} elseif ( preg_match( '/^- (.+)$/', $line, $m ) ) {
				if ( $in_ol ) {
					$result_lines[] = '</ol>';
					$in_ol = false;
				}
				if ( ! $in_ul ) {
					$result_lines[] = '<ul>';
					$in_ul = true;
				}
				$result_lines[] = '<li>' . $m[1] . '</li>';
			} else {
				if ( $in_ol ) {
					$result_lines[] = '</ol>';
					$in_ol = false;
				}
				if ( $in_ul ) {
					$result_lines[] = '</ul>';
					$in_ul = false;
				}
				$result_lines[] = $line;
			}
		}

		if ( $in_ol ) {
			$result_lines[] = '</ol>';
		}
		if ( $in_ul ) {
			$result_lines[] = '</ul>';
		}

		return implode( "\n", $result_lines );
	}

	/**
	 * Extract a named h2 section from HTML.
	 *
	 * @param string $html         Full HTML.
	 * @param string $section_name Section heading text.
	 * @return string Section content or empty string.
	 */
	private static function extract_section( string $html, string $section_name ): string {
		$pattern = '/<h2[^>]*>' . preg_quote( $section_name, '/' ) . '<\/h2>(.*?)(?=<h2|$)/is';
		if ( preg_match( $pattern, $html, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	/**
	 * Extract content between the first h1 and the first h2 as a description fallback.
	 *
	 * @param string $html Full HTML.
	 * @return string Content or empty string.
	 */
	private static function extract_first_section( string $html ): string {
		if ( preg_match( '/<h1[^>]*>.*?<\/h1>(.*?)(?=<h2|$)/is', $html, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}
}

GRFI_GitHub_Updater::init();
