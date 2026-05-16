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

	// =========================================================================
	// CONFIGURATION
	// =========================================================================

	private const GITHUB_USER        = 'guilamu';
	private const GITHUB_REPO        = 'gf-require-if';
	private const PLUGIN_FILE        = 'gravity-forms-require-if/gravity-forms-require-if.php';
	private const PLUGIN_SLUG        = 'gravity-forms-require-if';
	private const PLUGIN_NAME        = 'Gravity Forms Require If';
	private const PLUGIN_DESCRIPTION = 'Make the required state of supported Gravity Forms fields conditional — based on form field values, user roles, URL parameters, and more.';
	private const REQUIRES_WP        = '6.0';
	private const TESTED_WP          = '6.7';
	private const REQUIRES_PHP       = '8.0';
	private const REQUIRES_GF        = '2.5';
	private const TEXT_DOMAIN        = 'gf-require-if';

	// =========================================================================
	// CACHE SETTINGS
	// =========================================================================

	private const CACHE_KEY        = 'grfi_github_release';
	private const CACHE_EXPIRATION = 43200; // 12 hours.
	private const GITHUB_TOKEN     = '';

	// =========================================================================
	// IMPLEMENTATION
	// =========================================================================

	public static function init(): void {
		add_filter( 'update_plugins_github.com', array( self::class, 'check_for_update' ), 10, 4 );
		add_filter( 'plugins_api', array( self::class, 'plugin_info' ), 20, 3 );
		add_filter( 'plugins_api_result', array( self::class, 'finalize_plugin_info' ), PHP_INT_MAX, 3 );
		add_filter( 'upgrader_source_selection', array( self::class, 'fix_folder_name' ), 10, 4 );
		add_action( 'admin_head', array( self::class, 'plugin_info_css' ) );
	}

	public static function finalize_plugin_info( $result, $action, $args ) {
		if ( ! self::is_plugin_information_api_request( $action, $args ) ) {
			return $result;
		}

		return self::get_safe_plugin_info_result();
	}

	private static function is_plugin_information_api_request( $action, $args ): bool {
		return 'plugin_information' === $action
			&& is_object( $args )
			&& isset( $args->slug )
			&& self::PLUGIN_SLUG === $args->slug;
	}

	private static function get_plugin_file(): string {
		if ( defined( 'GRFI_FILE' ) && is_string( GRFI_FILE ) && '' !== GRFI_FILE ) {
			return plugin_basename( GRFI_FILE );
		}

		return self::PLUGIN_FILE;
	}

	private static function get_plugin_directory(): string {
		return dirname( self::get_plugin_file() );
	}

	private static function get_safe_plugin_info_result(): stdClass {
		static $plugin_info = null;

		if ( $plugin_info instanceof stdClass ) {
			return clone $plugin_info;
		}

		try {
			$plugin_info = self::build_plugin_info_result();
		} catch ( Throwable $throwable ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'%s plugin details fallback: %s in %s:%d',
					self::PLUGIN_NAME,
					$throwable->getMessage(),
					$throwable->getFile(),
					$throwable->getLine()
				) );
			}

			$plugin_info = self::build_fallback_plugin_info_result();
		}

		return clone $plugin_info;
	}

	private static function build_plugin_info_result(): stdClass {
		$release_data       = self::get_release_data();
		$installed_version  = defined( 'GRFI_VERSION' ) ? GRFI_VERSION : '1.0.0';
		$release_version    = $release_data ? ltrim( $release_data['tag_name'], 'v' ) : '';
		$display_version    = $installed_version;
		$has_update         = '' !== $release_version && version_compare( $release_version, $installed_version, '>' );

		if ( $has_update ) {
			$display_version = $release_version;
		}

		$result               = new stdClass();
		$result->name         = self::PLUGIN_NAME;
		$result->slug         = self::PLUGIN_SLUG;
		$result->plugin       = self::get_plugin_file();
		$result->version      = $display_version;
		$result->author       = sprintf( '<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER );
		$result->homepage     = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
		$result->requires     = self::REQUIRES_WP;
		$result->tested       = get_bloginfo( 'version' );
		$result->requires_php = self::REQUIRES_PHP;
		$result->external     = true;
		$result->banners      = array();
		$result->icons        = array();

		if ( $release_data && $has_update ) {
			$package_url = self::get_package_url( $release_data );

			if ( '' !== $package_url ) {
				$result->download_link = $package_url;
			}

			if ( ! empty( $release_data['published_at'] ) ) {
				$result->last_updated = $release_data['published_at'];
			}
		}

		$result->sections = self::build_plugin_info_sections(
			self::parse_readme(),
			$release_data,
			$installed_version,
			$display_version
		);

		return $result;
	}

	private static function build_plugin_info_sections(
		array $readme,
		?array $release_data,
		string $installed_version,
		string $display_version
	): array {
		$sections = array(
			'description' => ! empty( $readme['description'] )
				? $readme['description']
				: '<p>' . esc_html( self::PLUGIN_DESCRIPTION ) . '</p>',
		);

		if ( ! empty( $readme['installation'] ) ) {
			$sections['installation'] = $readme['installation'];
		}

		if ( ! empty( $readme['faq'] ) ) {
			$sections['faq'] = $readme['faq'];
		}

		$changelog_html = '';

		if (
			is_array( $release_data )
			&& ! empty( $release_data['body'] )
			&& version_compare( $installed_version, $display_version, '<' )
		) {
			$changelog_html .= '<h4>' . esc_html( $display_version ) . '</h4>'
				. self::markdown_to_html( (string) $release_data['body'] );
		}

		if ( ! empty( $readme['changelog'] ) ) {
			$changelog_html .= $readme['changelog'];
		}

		$sections['changelog'] = ! empty( $changelog_html )
			? $changelog_html
			: sprintf(
				'<p>See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.</p>',
				esc_attr( self::GITHUB_USER ),
				esc_attr( self::GITHUB_REPO )
			);

		return $sections;
	}

	private static function build_fallback_plugin_info_result(): stdClass {
		$result               = new stdClass();
		$result->name         = self::PLUGIN_NAME;
		$result->slug         = self::PLUGIN_SLUG;
		$result->plugin       = self::get_plugin_file();
		$result->version      = defined( 'GRFI_VERSION' ) ? GRFI_VERSION : '1.0.0';
		$result->author       = sprintf( '<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER );
		$result->homepage     = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
		$result->requires     = self::REQUIRES_WP;
		$result->tested       = get_bloginfo( 'version' );
		$result->requires_php = self::REQUIRES_PHP;
		$result->external     = true;
		$result->banners      = array();
		$result->icons        = array();
		$result->sections     = array(
			'description' => '<p>' . esc_html( self::PLUGIN_DESCRIPTION ) . '</p>',
			'changelog'   => sprintf(
				'<p>See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.</p>',
				esc_attr( self::GITHUB_USER ),
				esc_attr( self::GITHUB_REPO )
			),
		);

		return $result;
	}

	private static function is_plugin_info_request(): bool {
		if ( ! isset( $_GET['tab'], $_GET['plugin'] ) ) {
			return false;
		}

		$tab    = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
		$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );

		return 'plugin-information' === $tab && self::PLUGIN_SLUG === $plugin;
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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( self::PLUGIN_NAME . ' Update Error: ' . $response->get_error_message() );
			}

			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( self::PLUGIN_NAME . " Update Error: HTTP {$response_code}" );
			}

			return null;
		}

		$release_data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release_data['tag_name'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( self::PLUGIN_NAME . ' Update Error: No tag_name in release' );
			}

			return null;
		}

		set_transient( self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION );

		return $release_data;
	}

	private static function get_package_url( array $release_data ): string {
		if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
			foreach ( $release_data['assets'] as $asset ) {
				if (
					isset( $asset['browser_download_url'], $asset['name'] )
					&& str_ends_with( $asset['name'], '.zip' )
				) {
					return $asset['browser_download_url'];
				}
			}
		}

		return $release_data['zipball_url'] ?? '';
	}

	public static function check_for_update( $update, array $plugin_data, string $plugin_file, $locales ) {
		if ( self::get_plugin_file() !== $plugin_file ) {
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
			'plugin'        => self::get_plugin_file(),
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
		if ( ! self::is_plugin_information_api_request( $action, $args ) ) {
			return $res;
		}

		return self::get_safe_plugin_info_result();
	}

	public static function plugin_info_css(): void {
		if ( ! self::is_plugin_info_request() ) {
			return;
		}

		$pattern_css = '--s: 27px;'
			. '--c1: #b2b2b2;'
			. '--c2: #ffffff;'
			. '--c3: #d9d9d9;'
			. '--_g: var(--c3) 0 120deg, #0000 0;';

		$pattern_bg = 'conic-gradient(from -60deg at 50% calc(100%/3), var(--_g)),'
			. 'conic-gradient(from 120deg at 50% calc(200%/3), var(--_g)),'
			. 'conic-gradient(from 60deg at calc(200%/3), var(--c3) 60deg, var(--c2) 0 120deg, #0000 0),'
			. 'conic-gradient(from 180deg at calc(100%/3), var(--c1) 60deg, var(--_g)),'
			. 'linear-gradient(90deg, var(--c1) calc(100%/6), var(--c2) 0 50%,'
			. 'var(--c1) 0 calc(500%/6), var(--c2) 0)';

		echo '<style>'
			. '#plugin-information-title.with-banner {'
			. $pattern_css
			. 'background: ' . $pattern_bg . ' !important;'
			. 'background-size: calc(1.732 * var(--s)) var(--s) !important;'
			. '}'
			. '#plugin-information-title.with-banner h2 {'
			. 'position: relative;'
			. 'font-family: "Helvetica Neue", sans-serif;'
			. 'display: inline-block;'
			. 'font-size: 30px;'
			. 'line-height: 1.68;'
			. 'box-sizing: border-box;'
			. 'max-width: 100%;'
			. 'padding: 0 15px;'
			. 'margin-top: 174px;'
			. 'color: #fff;'
			. 'background: rgba(29, 35, 39, 0.9);'
			. 'text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);'
			. 'box-shadow: 0 0 30px rgba(255, 255, 255, 0.1);'
			. 'border-radius: 8px;'
			. '}'
			. '#section-holder .section h2 { margin: 1.5em 0 0.5em; clear: none; }'
			. '#section-holder .section h3 { margin: 1.5em 0 0.5em; }'
			. '#section-holder .section > :first-child { margin-top: 0; }'
			. '.md-table { display: table; width: 100%; border-collapse: collapse; margin: 1em 0; font-size: 13px; }'
			. '.md-tr { display: table-row; }'
			. '.md-tr > span { display: table-cell; padding: 6px 10px; border: 1px solid #ddd; vertical-align: top; }'
			. '.md-th > span { font-weight: 600; background: #f5f5f5; }'
			. '</style>';

		$gf_version = esc_html( self::REQUIRES_GF );
		echo '<script>'
			. 'document.addEventListener("DOMContentLoaded",function(){'
			. 'var title=document.getElementById("plugin-information-title");'
			. 'if(title){title.classList.add("with-banner");}'
			. 'var items=document.querySelectorAll(".fyi ul li");'
			. 'var php=null;'
			. 'for(var i=0;i<items.length;i++){if(items[i].textContent.indexOf("Requires PHP")!==-1){php=items[i];break;}}'
			. 'if(!php)return;'
			. 'var li=document.createElement("li");'
			. 'li.innerHTML="<strong>Requires Gravity Forms:<\/strong> ' . $gf_version . ' or higher";'
			. 'php.parentNode.insertBefore(li,php.nextSibling);'
			. '});'
			. '</script>';
	}

	// =========================================================================
	// README.md Parsing
	// =========================================================================

	private static function parse_readme(): array {
		$readme_path = WP_PLUGIN_DIR . '/' . self::get_plugin_directory() . '/README.md';

		if ( ! file_exists( $readme_path ) ) {
			return array();
		}

		$content = file_get_contents( $readme_path );
		if ( false === $content ) {
			return array();
		}

		// Remove the main title line (# Title).
		$content = preg_replace( '/^#\s+[^\n]+\n*/m', '', $content, 1 );

		// Sections that are NOT part of the description tab.
		$utility_sections = array(
			'changelog', 'requirements', 'installation', 'faq',
			'project structure', 'acknowledgements', 'license',
		);

		// Split content by ## headers.
		$parts = preg_split( '/^##\s+/m', $content );

		$description  = trim( $parts[0] ?? '' );
		$installation = '';
		$faq          = '';
		$changelog    = '';

		for ( $i = 1, $count = count( $parts ); $i < $count; $i++ ) {
			$lines = explode( "\n", $parts[ $i ], 2 );
			$title = strtolower( trim( $lines[0] ) );
			$body  = trim( $lines[1] ?? '' );

			if ( 'installation' === $title ) {
				$installation .= $body . "\n\n";
			} elseif ( 'faq' === $title ) {
				$faq .= $body . "\n\n";
			} elseif ( 'changelog' === $title ) {
				$changelog .= $body . "\n\n";
			} elseif ( ! in_array( $title, $utility_sections, true ) ) {
				$description .= "\n\n## " . trim( $lines[0] ) . "\n" . $body;
			}
		}

		return array(
			'description'  => self::markdown_to_html( trim( $description ) ),
			'installation' => self::markdown_to_html( trim( $installation ) ),
			'faq'          => self::markdown_to_html( trim( $faq ) ),
			'changelog'    => self::markdown_to_html( trim( $changelog ) ),
		);
	}

	private static function markdown_to_html( string $markdown ): string {
		if ( '' === $markdown ) {
			return '';
		}

		// Remove images (not useful in the modal).
		$markdown = preg_replace( '/!\[[^\]]*\]\([^\)]+\)/', '', $markdown );
		$markdown = preg_replace( '/<p\b[^>]*>\s*(?:(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:<\/a>\s*)?)+<\/p>\s*/is', '', $markdown );
		$markdown = preg_replace( '/(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:<\/a>)?/i', '', $markdown );

		if ( ! class_exists( 'Parsedown' ) ) {
			$parsedown_path = __DIR__ . '/Parsedown.php';

			if ( ! file_exists( $parsedown_path ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'%s plugin details fallback: missing Parsedown.php at %s',
						self::PLUGIN_NAME,
						$parsedown_path
					) );
				}

				return wpautop( esc_html( $markdown ) );
			}

			require_once $parsedown_path;
		}

		if ( ! class_exists( 'Parsedown' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'%s plugin details fallback: Parsedown class unavailable after load',
					self::PLUGIN_NAME
				) );
			}

			return wpautop( esc_html( $markdown ) );
		}

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true );

		$html = $parsedown->text( $markdown );

		// Convert <table> to wp_kses-safe <div>/<span> structures.
		$html = self::tables_to_divs( $html );

		return $html;
	}

	private static function tables_to_divs( string $html ): string {
		return preg_replace_callback( '/<table>(.*?)<\/table>/s', function ( $m ) {
			$table_html = $m[1];
			$output = '<div class="md-table">';

			preg_match_all( '/<tr>(.*?)<\/tr>/s', $table_html, $rows );

			foreach ( $rows[1] as $idx => $row_content ) {
				$is_header = ( 0 === $idx && strpos( $table_html, '<thead>' ) !== false );
				$row_class = $is_header ? 'md-tr md-th' : 'md-tr';

				preg_match_all( '/<t[hd]>(.*?)<\/t[hd]>/s', $row_content, $cells );

				$output .= '<div class="' . $row_class . '">';
				foreach ( $cells[1] as $cell ) {
					$output .= '<span>' . $cell . '</span>';
				}
				$output .= '</div>';
			}

			$output .= '</div>';
			return $output;
		}, $html );
	}

	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) ) {
			return $source;
		}

		if ( self::get_plugin_file() !== $hook_extra['plugin'] ) {
			return $source;
		}

		$correct_folder = self::get_plugin_directory();
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

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'%s updater: failed to rename update folder from %s to %s',
				self::PLUGIN_NAME,
				$source,
				$new_source
			) );
		}

		return new WP_Error(
			'rename_failed',
			__( 'Unable to rename the update folder. Please retry or update manually.', self::TEXT_DOMAIN )
		);
	}
}

GRFI_GitHub_Updater::init();
