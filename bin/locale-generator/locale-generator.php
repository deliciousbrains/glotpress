<?php
/**
 * Generate the locale object information in PHP format.
 *
 * @package GlotPress
 */

// Include the current locales.
include( '../../locales/locales.php' );

/*
 * Get the CLDR Data.
 *
 * By default it is read from the plurals.json file in the current directory, you can download
 * the file from the CLDR GitHub project:
 *
 *     https://raw.githubusercontent.com/unicode-cldr/cldr-core/master/supplemental/plurals.json
 *
 * or the script will do it automatically if the local file does not exist.
 *
 */
if ( ! file_exists( 'plurals.json' ) ) {
	file_put_contents( 'plurals.json', file_get_contents( 'https://raw.githubusercontent.com/unicode-cldr/cldr-core/master/supplemental/plurals.json' ) );
}

$cldr_data = json_decode( file_get_contents( 'plurals.json' ), true );

$cldr_locales = $cldr_data['supplemental']['plurals-type-cardinal'];

// Create a working locales object.
$locales = new GP_Locales();

// Run through the locales and see if we can find a matching CLDR locale.
foreach ( $locales->locales as $key => $value ) {
	// Flush the old CLDR data.
	unset( $locales->locales[ $key ]->cldr_code );
	unset( $locales->locales[ $key ]->cldr_nplurals );
	$locales->locales[ $key ]->cldr_plural_expressions = array(
		'zero'  => '',
		'one'   => '',
		'two'   => '',
		'few'   => '',
		'many'  => '',
		'other' => '',
	);

	// Check the iso codes against the CLDR data in accending order.
	if ( array_key_exists( $locales->locales[ $key ]->lang_code_iso_639_1, $cldr_locales ) ) {
		$locales->locales[ $key ]->cldr_code = $locales->locales[ $key ]->lang_code_iso_639_1;
	} elseif ( array_key_exists( $locales->locales[ $key ]->lang_code_iso_639_2, $cldr_locales ) ) {
		$locales->locales[ $key ]->cldr_code = $locales->locales[ $key ]->lang_code_iso_639_2;
	} elseif ( array_key_exists( $locales->locales[ $key ]->lang_code_iso_639_3, $cldr_locales ) ) {
		$locales->locales[ $key ]->cldr_code = $locales->locales[ $key ]->lang_code_iso_639_3;
	}

	// If we found a matching CLDR code, add the plurals data to the GP locales.
	if ( $locales->locales[ $key ]->cldr_code ) {
		// Set the number of CLDR plurals.
		$locales->locales[ $key ]->cldr_nplurals = count( $cldr_locales[ $locales->locales[ $key ]->cldr_code ] );

		// Loop through the CLDR plural rules and set them according to their type.
		foreach ( $cldr_locales[ $locales->locales[ $key ]->cldr_code ] as $type => $rule ) {
			switch ( $type ) {
				case 'pluralRule-count-zero':
					$locales->locales[ $key ]->cldr_plural_expressions['zero'] = $rule;

					break;
				case 'pluralRule-count-one':
					$locales->locales[ $key ]->cldr_plural_expressions['one'] = $rule;

					break;
				case 'pluralRule-count-two':
					$locales->locales[ $key ]->cldr_plural_expressions['two'] = $rule;

					break;
				case 'pluralRule-count-few':
					$locales->locales[ $key ]->cldr_plural_expressions['few'] = $rule;

					break;
				case 'pluralRule-count-many':
					$locales->locales[ $key ]->cldr_plural_expressions['many'] = $rule;

					break;
				case 'pluralRule-count-other':
					$locales->locales[ $key ]->cldr_plural_expressions['other'] = $rule;

					break;
			}
		}
	}
}

// Use the new GP Locales object to create output that can replace the locales.php file.
$variants = generate_output( $locales, true );

// Use the new GP Locales object to create output that can replace the locales.php file.
$novariants = generate_output( $locales, false );

// Delete any old backup file.
if ( file_exists( '../../locales/locales.backup.php' ) ) {
	unlink( '../../locales/locales.backup.php' );
}

// Rename the old locale file just in case.
rename( '../../locales/locales.php', '../../locales/locales.backup.php' );

// Write out the new default locales file.
file_put_contents( '../../locales/locales.php', $variants );

// Create the no variants directory if required.
if ( ! file_exists( '../../locales/no-variants' ) ) {
	mkdir( '../../locales/no-variants' );
}

// Delete any old backup file.
if ( file_exists( '../../locales/no-variant/locales.backup.php' ) ) {
	unlink( '../../locales/no-variants/locales.backup.php' );
}

// Rename the old locale file just in case.
if ( file_exists( '../../locales/no-variants/locales.php' ) ) {
	rename( '../../locales/no-variants/locales.php', '../../locales/no-variants/locales.backup.php' );
}

// Write out the no variants locales file.
file_put_contents( '../../locales/no-variants/locales.php', $novariants );

/**
 * Function to output the locales data in PHP source format.
 *
 * @param GP_Locales $locales The GlotPress Locales object.
 */
function generate_output( $locales, $include_variants = true ) {
	$output = '';

	// Read the old locales.php file in to memory.
	$locale_file = file_get_contents( '../../locales/locales.php' );

	// Trim off any extra training whitespace to avoid adding more later.
	$locale_file = rtrim( $locale_file );

	$locale_file_lines = explode( PHP_EOL, $locale_file );

	for( $current_line = 0; $current_line < sizeof( $locale_file_lines) ; $current_line++ ) {
		$output .= $locale_file_lines[ $current_line ] . PHP_EOL;

		if( "\t\t// START LOCALE DATA:" == substr( $locale_file_lines[ $current_line ], 0, 23 ) ) {
			break;
		}
	}

	// Get all the GP_Locale variables we're going to output.
	$vars = get_object_vars( $locales->locales['en'] );

	// If we're not outputting variants, unset the variant_root and variants properties.
	if ( false === $include_variants ) {
		unset( $vars[ 'variant_root' ] );
		unset( $vars[ 'variants' ] );
	}

	// Loop through all of the locales.
	foreach ( $locales->locales as $locale ) {
		// Create the variable name we'll use to define the locale based on the slug with any dashes replaced with underscores.
		$var_name = str_replace( '-', '_', $locale->slug );
		$root_var_name = str_replace( '-', '_', $locale->variant_root );

		// Output the first line to 'create' the GP_Locale object for this locale.
		$padding = str_repeat( ' ', 40 - strlen( $var_name ) );
		$output .= "\t\t\${$var_name}{$padding} = new GP_Locale();" . PHP_EOL;

		// Now loop through all the variables that may be set for this locale.
		foreach ( $vars as $var => $value ) {
			// Handle variables that are arrays for output.
			if ( is_array( $locale->$var ) ) {
				// Loop through all of the array keys to output.
				foreach ( $locale->$var as $key => $value ) {
					// Don't output empty variables or the speical case of 'variants' (they will be outputed with the variant locale instead).
					if ( ! empty( $locale->$var[ $key ] ) && 'variants' !== $var ) {
						// Create some space to make the output pretty and they output the line.
						$padding = str_repeat( ' ', 36 - strlen( $var ) - ( strlen( $var_name ) - 2 ) - strlen( $key ) - 4 );
						$output .= "\t\t\${$var_name}->{$var}['{$key}']{$padding} = '" . str_replace( "'", "\'", $locale->$var[ $key ] ) . "';" . PHP_EOL;
					}
				}
			} else {
				// Don't output empty variables.
				if ( ! empty( $locale->$var ) ) {
					// Create some space to make the output pretty and they output the line.
					$padding = str_repeat( ' ', 36 - strlen( $var ) - ( strlen( $var_name ) - 2 ) );
					$output .= "\t\t\${$var_name}->{$var}{$padding} = '" . str_replace( "'", "\'", $locale->$var ) . "';" . PHP_EOL;

					// Handle the special case of locales with variants, output the line to create the root's variant list here.
					if ( 'variant_root' === $var ) {
						// Create some space to make the output pretty and they output the line.
						$padding = str_repeat( ' ', 36 - strlen( $root_var_name ) - ( strlen( $root_var_name ) - 2 ) - 15 );
						$output .= "\t\t\${$root_var_name}->variants['{$var_name}']{$padding} = \${$root_var_name}->english_name;" . PHP_EOL;
					}
				}
			}
		}

		// Add some space between locales for easier reading.
		$output .= PHP_EOL;

	}

	for( ; $current_line < sizeof( $locale_file_lines) ; $current_line++ ) {
		if( "\t\t// END LOCALE DATA:" == substr( $locale_file_lines[ $current_line ], 0, 21 ) ) {
			break;
		}
	}

	for( ; $current_line < sizeof( $locale_file_lines) ; $current_line++ ) {
		$output .= $locale_file_lines[ $current_line ] . PHP_EOL;
	}

	return $output;
}
