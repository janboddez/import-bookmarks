<?php
/**
 * Plugin Name: Import Bookmarks
 * Description: Import browser bookmarks as WordPress posts.
 * Plugin URI:  https://jan.boddez.net/wordpress/import-bookmarks
 * Author:      Jan Boddez
 * Author URI:  https://jan.boddez.net/
 * License:     General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: import-bookmarks
 * Version:     0.3.0
 *
 * @package Import_Bookmarks
 */

namespace Import_Bookmarks;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/build/vendor/scoper-autoload.php';

/**
 * Main plugin class and settings.
 */
class Bookmarks_Importer {
	/**
	 * WordPress' default post types, sans 'post'.
	 *
	 * @var array DEFAULT_POST_TYPES Default post types, minus 'post' itself.
	 *
	 * @since 0.2.6
	 */
	const DEFAULT_POST_TYPES = array(
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'user_request',
		'oembed_cache',
		'wp_block',
		'coblocks_pattern',
	);

	/**
	 * Allowable post statuses.
	 *
	 * @var array POST_STATUSES Allowable post statuses.
	 *
	 * @since 0.2.6
	 */
	const POST_STATUSES = array(
		'publish',
		'draft',
		'pending',
		'private',
	);

	/**
	 * Registers actions.
	 *
	 * @since 0.3.0
	 */
	public function register() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
		add_action( 'admin_post_import_bookmarks', array( $this, 'import' ) );
	}

	/**
	 * Enables i18n of this plugin.
	 *
	 * @since 0.2.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'import-bookmarks', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Registers the plugin 'Tools' page.
	 *
	 * @since 0.1.0
	 */
	public function create_menu() {
		add_management_page(
			__( 'Import Bookmarks', 'import-bookmarks' ),
			__( 'Import Bookmarks', 'import-bookmarks' ),
			'import',
			'import-bookmarks',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Echoes the upload form.
	 *
	 * @since 0.1.0
	 */
	public function settings_page() {
		$options      = get_option( 'import_bookmarks', array() );
		$post_types   = array_diff( get_post_types(), self::DEFAULT_POST_TYPES );
		$post_formats = get_post_format_slugs();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Bookmarks', 'import-bookmarks' ); ?></h1>

			<form action="admin-post.php" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'import-bookmarks-run' ); ?>
				<input type="hidden" name="action" value="import_bookmarks">

				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="bookmarks-file"><?php esc_html_e( 'Bookmarks File', 'import-bookmarks' ); ?></label></th>
						<td>
							<input type="file" name="bookmarks_file" id="bookmarks-file" accept="text/html">
							<p class="description"><?php esc_html_e( 'Bookmarks HTML file to be imported.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="post-type"><?php esc_html_e( 'Post Type', 'import-bookmarks' ); ?></label></th>
						<td>
							<select name="post_type" id="post-type">
								<?php
								foreach ( $post_types as $post_type ) :
									$post_type_object = get_post_type_object( $post_type );
									?>
									<option value="<?php echo esc_attr( $post_type ); ?>" <?php ( ! empty( $options['post_type'] ) ? selected( $post_type, $options['post_type'] ) : '' ); ?>>
										<?php echo esc_html( $post_type_object->labels->singular_name ); ?>
									</option>
									<?php
								endforeach;
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Imported bookmarks will be of this type.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="post-status"><?php esc_html_e( 'Post Status', 'import-bookmarks' ); ?></label></th>
						<td>
							<select name="post_status" id="post-status">
								<?php foreach ( self::POST_STATUSES as $post_status ) : ?>
									<option value="<?php echo esc_attr( $post_status ); ?>" <?php ( ! empty( $options['post_status'] ) ? selected( $post_status, $options['post_status'] ) : '' ); ?>><?php echo esc_html( ucfirst( $post_status ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Imported bookmarks will receive this status.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="post-format"><?php esc_html_e( 'Post Format', 'import-bookmarks' ); ?></label></th>
						<td>
							<select name="post_format" id="post-format">
								<?php foreach ( $post_formats as $post_format ) : ?>
									<option value="<?php echo esc_attr( $post_format ); ?>" <?php ( ! empty( $options['post_format'] ) ? selected( $post_format, $options['post_format'] ) : '' ); ?>><?php echo esc_html( get_post_format_string( $post_format ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Affects only Post Types that actually support Post Formats. Your active theme decides how different Post Formats are displayed. Regardless, &ldquo;Link&rdquo; is probably a good idea.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Skip Duplicates', 'import-bookmarks' ); ?></th>
						<td>
							<label><input type="checkbox" name="skip_duplicates" value="1" <?php checked( ! empty( $options['skip_duplicates'] ) ); ?>> <?php esc_html_e( 'Skip duplicates', 'import-bookmarks' ); ?></label>
							<p class="description"><?php esc_html_e( 'Prevent importing the same bookmark twice. Only the selected Post Type is taken into account. (Note: For this to work, the Post Type must support Custom Fields!)', 'import-bookmarks' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit"><?php submit_button( __( 'Import Bookmarks', 'import-bookmarks' ), 'primary', 'submit', false ); ?></p>
			</form>
		</div>

		<?php
		if ( ! empty( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'import-bookmarks-success' ) ) :
			if ( isset( $_GET['imported'] ) && ctype_digit( $_GET['imported'] ) && isset( $_GET['skipped'] ) && ctype_digit( $_GET['skipped'] ) ) : // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				?>
				<div class="notice notice-success is-dismissible">
					<?php /* translators: %1$d number of imported bookmarks %2$d number of skipped bookmarks */ ?>
					<p><?php printf( esc_html__( '%1$d bookmarks imported (and %2$d skipped)!', 'import-bookmarks' ), intval( $_GET['imported'] ), intval( $_GET['skipped'] ) ); ?></p>
				</div>
				<?php
			endif;
		endif;
	}

	/**
	 * Runs the importer after a file was uploaded.
	 *
	 * @since 0.1.0
	 */
	public function import() {
		if ( ! current_user_can( 'import' ) ) {
			wp_die( esc_html__( 'You have insufficient permissions to access this page.', 'import-bookmarks' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'import-bookmarks-run' ) ) {
			wp_die( esc_html__( 'This page should not be accessed directly.', 'import-bookmarks' ) );
		}

		if ( empty( $_FILES['bookmarks_file'] ) ) {
			wp_die( esc_html__( 'Something went wrong uploading the file.', 'import-bookmarks' ) );
		}

		// Let WordPress handle the uploaded file.
		$uploaded_file = wp_handle_upload(
			$_FILES['bookmarks_file'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			array(
				'test_form' => false,
				'mimes'     => array( 'htm|html' => 'text/html' ),
			)
		);

		if ( ! empty( $uploaded_file['error'] ) && is_string( $uploaded_file['error'] ) ) {
			// `wp_handle_upload()` returned an error.
			wp_die( esc_html( $uploaded_file['error'] ) );
		} elseif ( empty( $uploaded_file['file'] ) || ! is_string( $uploaded_file['file'] ) ) {
			wp_die( esc_html__( 'Something went wrong uploading the file.', 'import-bookmarks' ) );
		}

		$options = get_option( 'import_bookmarks', array() );

		// Allowed post types.
		$post_types = array_diff( get_post_types(), self::DEFAULT_POST_TYPES );

		// Default post type.
		$post_type = 'post';

		if ( ! empty( $_POST['post_type'] ) && in_array( wp_unslash( $_POST['post_type'] ), $post_types, true ) ) {
			$post_type = wp_unslash( $_POST['post_type'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		// Default post status.
		$post_status = 'publish';

		if ( ! empty( $_POST['post_status'] ) && in_array( wp_unslash( $_POST['post_status'] ), self::POST_STATUSES, true ) ) {
			$post_status = wp_unslash( $_POST['post_status'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		// Default post format.
		$post_format = 'standard';

		if ( ! empty( $_POST['post_format'] ) && in_array( wp_unslash( $_POST['post_format'] ), get_post_format_slugs(), true ) ) {
			$post_format = wp_unslash( $_POST['post_format'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		// Default "skip" setting.
		$skip_duplicates = false;

		if ( ! empty( $_POST['skip_duplicates'] ) && '1' === $_POST['skip_duplicates'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$skip_duplicates = true;
		}

		// Save settings, for next time.
		$options['post_type']       = $post_type;
		$options['post_status']     = $post_status;
		$options['post_format']     = $post_format;
		$options['skip_duplicates'] = $skip_duplicates;

		update_option( 'import_bookmarks', $options, false );

		$parser    = new Shaarli\NetscapeBookmarkParser\NetscapeBookmarkParser();
		$bookmarks = $parser->parseFile( $uploaded_file['file'] );

		if ( empty( $bookmarks ) || ! is_array( $bookmarks ) ) {
			wp_die( esc_html__( 'Empty or invalid bookmarks file.', 'import-bookmarks' ) );
		}

		$imported = 0;
		$skipped  = 0;

		foreach ( $bookmarks as $bookmark ) {
			if ( false === filter_var( $bookmark['uri'], FILTER_VALIDATE_URL ) ) {
				// Skip invalid "URLs," like those that start with `place:`.
				/* translators: %s: invalid URL */
				error_log( sprintf( __( 'Skipping %s (invalid).', 'import-bookmarks' ), $bookmark['uri'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				continue;
			}

			if ( $skip_duplicates ) {
				// Requires custom field support for `$post_type`!
				$query = new \WP_Query(
					array(
						'post_type'           => $post_type, // The selected post type.
						'posts_per_page'      => -1,
						'ignore_sticky_posts' => '1',
						'fields'              => 'ids',
						'meta_key'            => 'import_bookmarks_uri', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'          => esc_url_raw( $bookmark['uri'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				);

				if ( ! empty( $query->posts ) ) {
					$skipped++;

					/* translators: %s: duplicate URL */
					error_log( sprintf( __( 'Skipping %s (duplicate).', 'import-bookmarks' ), $bookmark['uri'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					continue;
				}
			}

			$post_title    = sanitize_text_field( $bookmark['title'] );
			$post_content  = sanitize_text_field( $bookmark['note'] );
			$post_content .= PHP_EOL . PHP_EOL . '<a href="' . esc_url( $bookmark['uri'] ) . '">' . $post_title . '</a>';
			$post_content  = trim( $post_content );

			/**
			 * Allow filtering the post's HTML.
			 *
			 * @since 2.1.0
			 */
			$post_content = apply_filters( 'import_bookmarks_post_content', $post_content, $bookmark, $post_type );

			$post_id = wp_insert_post(
				array(
					'post_title'   => $post_title,
					'post_content' => $post_content,
					'post_status'  => $post_status,
					'post_type'    => $post_type,
					'post_date'    => get_date_from_gmt( date( 'Y-m-d H:i:s', $bookmark['time'] ), 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				)
			);

			if ( $post_id ) {
				$imported++;

				if ( post_type_supports( $post_type, 'custom-fields' ) ) {
					update_post_meta( $post_id, 'import_bookmarks_uri', esc_url_raw( $bookmark['uri'] ) );
				}

				if ( post_type_supports( $post_type, 'post-formats' ) ) {
					set_post_format( $post_id, $post_format );
				}
			}
		}

		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'page'     => 'import-bookmarks',
						'imported' => $imported,
						'skipped'  => $skipped,
						'_wpnonce' => wp_create_nonce( 'import-bookmarks-success' ),
					),
					admin_url( 'tools.php' )
				)
			)
		);
		exit;
	}
}

$import_bookmarks = new Bookmarks_Importer();
$import_bookmarks->register();
