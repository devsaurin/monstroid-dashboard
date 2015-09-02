<?php
/**
 * Theme backups management class
 *
 * @package   monstroid_dashboard
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

class Monstroid_Dashboard_Backup_Manager {

	/**
	 * Path to backup file
	 * @var string
	 */
	public $path;

	/**
	 * Theme files to backup
	 * @var array
	 */
	public $files = array();

	/**
	 * Messages holder
	 * @var string
	 */
	public $message = null;

	/**
	 * A reference to an instance of this class.
	 *
	 * @since 1.0.0
	 * @var   object
	 */
	private static $instance = null;

	function __construct() {

		// connect filesystem
		$this->fs_connect();

		// set path
		$upload_dir      = wp_upload_dir();
		$upload_base_dir = $upload_dir['basedir'];
		$this->path      = trailingslashit( $upload_base_dir ) . 'update-backups';

	}

	/**
	 * Try to create backup archive
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function make_backup() {

		global $wp_filesystem;

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		include_once( ABSPATH . '/wp-admin/includes/class-pclzip.php' );

		if ( ! $this->check_path() ) {
			return false;
		}

		$this->protect_path();

		ini_set( 'max_execution_time', -1 );
		set_time_limit( 0 );

		$zip_name    = $this->path . '/monstroid-' . monstroid_dashboard_updater()->get_current_version() . '.zip';
		$files       = $this->get_files();
		$files       = implode( ',', $files );
		$remove_path = $wp_filesystem->wp_themes_dir();
		$zip         = new PclZip( $zip_name );
		$result      = $zip->create( $files, PCLZIP_OPT_REMOVE_PATH, $remove_path );

		if ( ! $result ) {
			return false;
		}

		return str_replace( ABSPATH, home_url( '/' ), $zip_name );

	}

	/**
	 * Get backup files list
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_files() {
		global $wp_filesystem;
		$path = $wp_filesystem->wp_themes_dir() . 'monstroid';
		$this->parse_dir( $path );
		return $this->files;
	}

	public function parse_dir( $dir ) {
		global $wp_filesystem;

		foreach ( $wp_filesystem->dirlist( $dir ) as $name => $data ) {
			$current_path = trailingslashit( $dir ) . $name;
			if ( 'd' == $data['type'] ) {
				$this->parse_dir( $current_path );
				continue;
			}

			$this->files[] = $current_path;
		}

	}

	/**
	 * Check if backup directory exists and create it if not
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function check_path() {

		global $wp_filesystem;
		$path = $this->prepare_path( $this->path );

		if ( $wp_filesystem->exists( $path ) ) {
			return true;
		}

		return $wp_filesystem->mkdir( $path );

	}

	/**
	 * Create .htaccess file in updates backup dir to protect it from direct access
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function protect_path() {

		global $wp_filesystem;
		$path = $this->prepare_path( $this->path );

		$file = $path . '/.htaccess';

		if ( $wp_filesystem->exists( $file ) ) {
			return true;
		}

		$wp_filesystem->put_contents( $file, 'deny from all' );

	}

	/**
	 * Prepeare path for using with filesystem API
	 *
	 * @since  1.0.0
	 * @param  string $path
	 * @return string
	 */
	public function prepare_path( $path ) {
		global $wp_filesystem;
		return str_replace( ABSPATH, $wp_filesystem->abspath(), $path );
	}

	/**
	 * Get avaliable backups list
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_backups() {

		global $wp_filesystem;
		$path  = $this->prepare_path( $this->path );
		$files = $wp_filesystem->dirlist( $path );

		if ( isset( $files['.htaccess'] ) ) {
			unset( $files['.htaccess'] );
		}

		$result = array();

		foreach ( $files as $file => $data ) {
			if ( 'zip' !== pathinfo( $file, PATHINFO_EXTENSION ) ) {
				continue;
			}

			$result[] = array(
				'name' => $file,
				'date' => date( 'M d Y, H:i', $data['lastmodunix'] )
			);
		}

		usort( $result, array( $this, 'date_compare' ) );

		return $result;

	}

	/**
	 * Compare backups by date
	 *
	 * @param  array $a 1st value
	 * @param  array $b 2nd value
	 * @return bool
	 */
	public function date_compare( $a, $b ) {
		$t1 = strtotime( $a['date'] );
		$t2 = strtotime( $b['date'] );
		return $t2 - $t1;
	}

	/**
	 * Download backup by filename
	 *
	 * @since  1.0.0
	 * @param  string $file backup filename
	 * @return void
	 */
	public function download_backup( $file ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->message = __( 'Permission denied', 'monstroid-dashboard' );
			return false;
		}

		global $wp_filesystem;

		$path     = $this->prepare_path( $this->path );
		$filepath = $path . '/' . $file;

		if ( ! $wp_filesystem->exists( $filepath ) ) {
			$this->message = __( 'File not exists', 'monstroid-dashboard' );
			return false;
		}

		session_write_close();

		header( "Pragma: public" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: public" );
		header( "Content-Description: File Transfer" );
		header( "Content-type: application/octet-stream" );
		header( "Content-Disposition: attachment; filename=\"" . $file . "\"" );
		header( "Content-Transfer-Encoding: binary" );
		header( "Content-Length: " . @filesize( $filepath ) );

		$this->readfile_chunked( $filepath ) or header( 'Location: ' . $filepath );

		exit();

	}

	/**
	 * Delete existing backup by filename
	 *
	 * @since  1.0.0
	 * @param  string $file backup filename
	 * @return void
	 */
	public function delete_backup( $file ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->message = __( 'Permission denied', 'monstroid-dashboard' );
			return false;
		}

		global $wp_filesystem;

		$path     = $this->prepare_path( $this->path );
		$filepath = $path . '/' . $file;

		if ( ! $wp_filesystem->exists( $filepath ) ) {
			$this->message = __( 'File not exists', 'monstroid-dashboard' );
			return false;
		}

		$delete = $wp_filesystem->delete( $filepath );

		if ( false === $delete ) {
			$this->message = __( 'Can\'t delete selected backup', 'monstroid-dashboard' );
		}

		return $delete;

	}

	/**
	 * Chunked file reading
	 *
	 * @since  1.0.0
	 * @param  string  $file     fileptah
	 * @param  boolean $retbytes return bytes number or not
	 * @return bool|int
	 */
	function readfile_chunked( $file, $retbytes = true ) {

		$chunksize = 1024 * 1024;
		$buffer    = '';
		$cnt       = 0;
		$handle    = @fopen( $file, 'r' );

		if ( $size = @filesize( $file ) ) {
			header("Content-Length: " . $size );
		}

		if ( false === $handle ) {
			return false;
		}

		while ( ! @feof( $handle ) ) {
			$buffer = @fread( $handle, $chunksize );
			echo $buffer;
			ob_flush();
			flush();
			if ( $retbytes ) {
				$cnt += strlen( $buffer );
			}
		}

		$status = @fclose( $handle );

		if ( $retbytes && $status ) {
			return $cnt;
		}

		return $status;
	}

	/**
	 * Check if backup manager returned any messages during processing
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_message() {
		return (string) $this->message;
	}


	/**
	 * Connect to the filesystem.
	 *
	 * @since 1.0.0
	 *
	 * @param array $directories                  Optional. A list of directories. If any of these do
	 *                                            not exist, a {@see WP_Error} object will be returned.
	 *                                            Default empty array.
	 * @param bool  $allow_relaxed_file_ownership Whether to allow relaxed file ownership.
	 *                                            Default false.
	 * @return bool|WP_Error True if able to connect, false or a {@see WP_Error} otherwise.
	 */
	public function fs_connect( $directories = array(), $allow_relaxed_file_ownership = false ) {

		global $wp_filesystem;

		$url = admin_url( 'options.php' );

		if ( false === ( $credentials = request_filesystem_credentials( $url, '', false, false, array(), $allow_relaxed_file_ownership ) ) ) {
			return false;
		}

		if ( ! empty( $directories[0] ) ) {
			$dirs = $directories[0];
		} else {
			$dirs = array();
		}

		if ( ! WP_Filesystem( $credentials, $dirs, $allow_relaxed_file_ownership ) ) {
			$error = true;
			if ( is_object($wp_filesystem) && $wp_filesystem->errors->get_error_code() ) {
				$error = $wp_filesystem->errors;
			}
			return false;
		}

		if ( ! is_object($wp_filesystem) )
			return new WP_Error('fs_unavailable', $this->strings['fs_unavailable'] );

		if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
			return new WP_Error('fs_error', $this->strings['fs_error'], $wp_filesystem->errors);

		foreach ( (array)$directories as $dir ) {
			switch ( $dir ) {
				case ABSPATH:
					if ( ! $wp_filesystem->abspath() )
						return new WP_Error('fs_no_root_dir', $this->strings['fs_no_root_dir']);
					break;
				case WP_CONTENT_DIR:
					if ( ! $wp_filesystem->wp_content_dir() )
						return new WP_Error('fs_no_content_dir', $this->strings['fs_no_content_dir']);
					break;
				case WP_PLUGIN_DIR:
					if ( ! $wp_filesystem->wp_plugins_dir() )
						return new WP_Error('fs_no_plugins_dir', $this->strings['fs_no_plugins_dir']);
					break;
				case get_theme_root():
					if ( ! $wp_filesystem->wp_themes_dir() )
						return new WP_Error('fs_no_themes_dir', $this->strings['fs_no_themes_dir']);
					break;
				default:
					if ( ! $wp_filesystem->find_folder($dir) )
						return new WP_Error( 'fs_no_folder', sprintf( $this->strings['fs_no_folder'], esc_html( basename( $dir ) ) ) );
					break;
			}
		}

		return true;
	}

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @return object
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

}