<?php
/**
 * Routes: GP_Route_Local class
 *
 * @package GlotPress
 * @subpackage Routes
 * @since 4.0.0
 */

/**
 * Core class used to implement the local translation route.
 *
 * @since 4.0.0
 */
class GP_Route_Local extends GP_Route_Main {
	/**
	 * Imports the originals and translations for the WordPress core, a plugin or a theme.
	 *
	 * @param string $project_path The project_path of the core, plugin, or theme.
	 */
	public function import( string $project_path ) {
		$this->security_checks( $project_path );
		$locale  = GP_Locales::by_field( 'wp_locale', gp_post( 'locale' ) );
		$project = $this->create_project_and_import_strings( gp_post( 'name' ), $project_path, gp_post( 'description' ), $locale, gp_post( 'locale_slug', 'default' ) );
		if ( $project ) {
			$this->redirect(
				gp_url(
					sprintf(
						'/projects/%s/%s/default',
						$project->path,
						$locale->slug
					)
				)
			);
		}
	}

	/**
	 * Checks the nonce and the permissions.
	 *
	 * @param string $project_path The project_path of the core, plugin, or theme.
	 */
	private function security_checks( string $project_path ) {
		$element_to_check = 'gp-local-' . $project_path;
		if ( ! wp_verify_nonce( gp_post( '_wpnonce' ), $element_to_check ) ) {
			wp_die( esc_html__( 'Your nonce could not be verified.', 'glotpress' ) );
		}
		if ( ! $this->can( 'write', 'project', null ) ) {
			wp_die( esc_html__( 'You are not allowed to do that!', 'glotpress' ) );
		}
	}

	/**
	 * Creates a project and import the strings.
	 *
	 * @param string    $project_name The name of the project.
	 * @param string    $path The path of the project. The last element of the path is the slug.
	 * @param string    $project_description The description of the project.
	 * @param GP_Locale $locale The locale.
	 * @param string    $locale_slug The locale slug.
	 *
	 * @return     GP_Project  The gp project.
	 */
	private function create_project_and_import_strings( string $project_name, string $path, string $project_description, GP_Locale $locale, string $locale_slug ): GP_Project {
		$project        = $this->get_or_create_project( $project_name, apply_filters( 'gp_local_project_path', $path ), $project_description );
		$slug           = basename( $path );
		$languages_dir  = trailingslashit( ABSPATH ) . 'wp-content/languages/';
		$file_to_import = apply_filters( 'gp_local_project_po', $languages_dir . $slug . '-' . $locale->wp_locale . '.po', $path, $slug, $locale, $languages_dir );

		$translation_set = $this->get_or_create_translation_set( $project, $locale_slug, $locale );
		$this->get_or_import_originals( $project, $file_to_import );
		$this->get_or_import_translations( $project, $translation_set, $file_to_import );
		return $project;
	}

	/**
	 * Gets or creates a project.
	 *
	 * @param string $name The name of the project.
	 * @param string $path The path of the project. The last element of the path is the slug.
	 * @param string $description The description of the project.
	 *
	 * @return GP_Project
	 */
	private function get_or_create_project( string $name, string $path, string $description ): GP_Project {
		$project = GP::$project->by_path( $path );

		if ( ! $project ) {
			$path_separator = '';
			$project_path    = '';
			$parent_project  = null;
			$path_snippets   = explode( '/', $path );
			$project_slug    = array_pop( $path_snippets );
			foreach ( $path_snippets as $slug ) {
				$project_path  .= $path_separator . $slug;
				$path_separator = '/';
				$project        = GP::$project->by_path( $project_path );
				if ( ! $project ) {
					$new_project = new GP_Project(
						array(
							'name'              => GP::$local->get_project_name( $project_path ),
							'slug'              => $slug,
							'description'       => GP::$local->get_project_description( $project_path ),
							'parent_project_id' => $parent_project ? $parent_project->id : null,
							'active'            => true,
						)
					);
					$project     = GP::$project->create_and_select( $new_project );
				}
				$parent_project = $project;
			}

			$new_project = new GP_Project(
				array(
					'name'              => $name,
					'slug'              => $project_slug,
					'description'       => $description,
					'parent_project_id' => $parent_project ? $parent_project->id : null,
					'active'            => true,
				)
			);
			$project     = GP::$project->create_and_select( $new_project );
		}
		return $project;
	}


	/**
	 * Gets or creates a translation set.
	 *
	 * @param GP_Project $project The project.
	 * @param string     $slug    The slug of the translation set.
	 * @param GP_Locale  $locale  The locale of the translation set.
	 *
	 * @return GP_Translation_Set
	 */
	private function get_or_create_translation_set( GP_Project $project, string $slug, GP_Locale $locale ): GP_Translation_Set {
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale(
			$project->id,
			$slug,
			$locale->slug
		);
		if ( ! $translation_set ) {
			$new_set         = new GP_Translation_Set(
				array(
					'name'       => $locale->english_name,
					'slug'       => $slug,
					'project_id' => $project->id,
					'locale'     => $locale->slug,
				)
			);
			$translation_set = GP::$translation_set->create_and_select( $new_set );
		}
		return $translation_set;
	}

	/**
	 * Gets or imports the originals.
	 *
	 * @param GP_Project $project   The project.
	 * @param string     $file_path The file to import.
	 *
	 * @return array
	 */
	private function get_or_import_originals( GP_Project $project, string $file_path ): array {
		$file_path = $this->get_po_file_path( $project, $file_path );
		if ( ! $file_path ) {
			return array();
		}

		$originals = GP::$original->by_project_id( $project->id );
		if ( ! $originals ) {
			$format    = 'po';
			$format    = gp_array_get( GP::$formats, $format, null );
			$originals = $format->read_originals_from_file( $file_path, $project );
			$originals = GP::$original->import_for_project( $project, $originals );
		}
		return $originals;
	}

	/**
	 * Gets or imports the translations.
	 *
	 * @param GP_Project         $project         The project.
	 * @param GP_Translation_Set $translation_set The translation set.
	 * @param string             $file_path       The file path to import.
	 *
	 * @return array
	 */
	private function get_or_import_translations( GP_Project $project, GP_Translation_Set $translation_set, string $file_path ):array {
		$file_path = $this->get_po_file_path( $project, $file_path );
		if ( ! file_exists( $file_path ) ) {
			return array();
		}
		$translations = GP::$translation->for_export( $project, $translation_set, array( 'status' => 'current' ) );
		if ( ! $translations ) {
			$po       = new PO();
			$imported = $po->import_from_file( $file_path );

			add_filter( 'gp_translation_prepare_for_save', array( $this, 'translation_import_overrides' ) );
			$translation_set->import( $po, 'current' );

			$translations = GP::$translation->for_export( $project, $translation_set, array( 'status' => 'current' ) );
		}
		return $translations;
	}

	/**
	 * Override the saved translation.
	 *
	 * @param      array $fields   The fields.
	 *
	 * @return     array  The updated fields.
	 */
	public function translation_import_overrides( $fields ) {
		// Discard warnings of current strings upon import.
		if ( ! empty( $fields['warnings'] ) ) {
			unset( $fields['warnings'] );
			$fields['status'] = 'current';
		}

		// Don't set the user id upon import so that we can later identify translations by users.
		unset( $fields['user_id'] );

		return $fields;
	}

	/**
	 * Gets the path to the .po file.
	 *
	 * Checks if the file exists in the WordPress "languages" folder.
	 * If not, downloads it from translate.w.org.
	 * If not, gets the translation from the project "languages" folder.
	 * If not, returns an empty string.
	 *
	 * @param GP_Project $project   The project.
	 * @param string     $file_path The file to import.
	 *
	 * @return string The path to the .po file.
	 */
	private function get_po_file_path( GP_Project $project, string $file_path ): string {
		if ( ! file_exists( $file_path ) ) {
			$file_path = $this->download_dotorg_translation( $project );
			if ( ! file_exists( $file_path ) ) {
				$file_path = $this->get_translation_file_path_from_project( $project );
				if ( ! file_exists( $file_path ) ) {
					return '';
				}
			}
		}
		return $file_path;
	}

	/**
	 * Downloads the translations from translate.w.org.
	 *
	 * Downloads the .po and .mo files, stores them in the
	 * - wp-content/languages/plugins/ or
	 * - wp-content/languages/themes/
	 *
	 * @param GP_Project $project The project.
	 *
	 * @return string The path to the .po file.
	 */
	private function download_dotorg_translation( GP_Project $project ): string {
		$locale       = GP_Locales::by_field( 'wp_locale', get_user_locale() );
		$project_type = '';
		$po_file_url  = '';
		$mo_file_url  = '';
		switch ( strtok( $project->path, '/' ) ) {
			case 'local-plugins':
				// Stable branch.
				$po_file_url  = sprintf(
					'https://translate.wordpress.org/projects/wp-plugins/%s/stable/%s/default/export-translations/?filters%%status%%5D=current_or_waiting_or_fuzzy_or_untranslated',
					$project->slug,
					$locale->slug
				);
				$project_type = 'plugins';
				break;
			case 'local-themes':
				$po_file_url  = sprintf(
					'https://translate.wordpress.org/projects/wp-themes/%s/%s/default/export-translations/?filters%%5Bstatus%%5D=current_or_waiting_or_fuzzy_or_untranslated',
					$project->slug,
					$locale->slug
				);
				$project_type = 'themes';
				break;
			default:
				return '';
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$po_tmp_file = download_url( $po_file_url );
		if ( ! $po_tmp_file || is_wp_error( $po_tmp_file ) ) {
			return '';
		}
		$mo_tmp_file = download_url( $po_file_url . '&format=mo' );
		if ( ! $mo_tmp_file || is_wp_error( $po_tmp_file ) ) {
			return '';
		}

		$po_file_destination = sprintf(
			'%swp-content/languages/%s/%s-%s.po',
			ABSPATH,
			$project_type,
			$project->slug,
			$locale->wp_locale
		);
		$mo_file_destination = sprintf(
			'%swp-content/languages/%s/%s-%s.mo',
			ABSPATH,
			$project_type,
			$project->slug,
			$locale->wp_locale
		);
		// Move the .po and .mo files to the WordPress "languages" folder.
		rename( $po_tmp_file, $po_file_destination );
		rename( $mo_tmp_file, $mo_file_destination );
		return $po_file_destination;
	}

	/**
	 * Gets the path to the .po file from the project.
	 *
	 * @param GP_Project $project The project.
	 *
	 * @return string The path to the .po file.
	 */
	private function get_translation_file_path_from_project( GP_Project $project ): string {
		$project_type = '';
		$locale       = GP_Locales::by_field( 'wp_locale', get_user_locale() );

		switch ( strtok( $project->path, '/' ) ) {
			case 'local-plugins':
				$project_type = 'plugins';
				break;
			case 'local-themes':
				$project_type = 'themes';
				break;
		}

		if ( ! $project_type ) {
			return '';
		}
		return sprintf(
			'%swp-content/%s/%s/languages/%s-%s.po',
			ABSPATH,
			$project_type,
			$project->slug,
			$project->slug,
			$locale->wp_locale
		);
	}
}