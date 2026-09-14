<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Парсер и записвач на .po/.mo файлове.
 * Обработва четенето на непреведени низове, контексти (msgctxt), множествени форми и атомарния запис.
 */
class Po_Handler {

	/**
	 * Парсира .po файл и връща структурирани записи.
	 *
	 * @param  string $file_path Абсолютен път до .po файла.
	 * @return array|\WP_Error
	 */
	public static function parse( string $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error(
				'file_not_found',
				sprintf(
					/* translators: %s: file path */
					__( 'PO file was not found: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$file_path
				)
			);
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error(
				'not_readable',
				sprintf(
					/* translators: %s: file path */
					__( 'PO file is not readable: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$file_path
				)
			);
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return new \WP_Error(
				'read_error',
				__( 'Error reading PO file.', 'ewa-ai-string-assistant-for-loco-translate' )
			);
		}

		return self::parse_content( $content );
	}

	/**
	 * Парсира съдържанието на .po файл в масив от записи.
	 *
	 * @param  string $content
	 * @return array
	 */
	public static function parse_content( string $content ) {
		$entries = [];
		$blocks  = preg_split( '/\n{2,}/', $content );

		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( empty( $block ) ) {
				continue;
			}

			$entry = self::parse_block( $block );
			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Парсира единичен PO блок (коментари + msgctxt + msgid + msgstr).
	 *
	 * @param  string $block
	 * @return array|null
	 */
	private static function parse_block( string $block ) {
		$lines = explode( "\n", $block );
		$entry = [
			'comments'      => [],
			'references'    => [],
			'flags'         => [],
			'msgctxt'       => null,
			'msgid'         => '',
			'msgid_plural'  => null,
			'msgstr'        => '',
			'msgstr_plural' => [],
			'is_header'     => false,
			'is_fuzzy'      => false,
			'raw'           => $block,
		];

		$current = null;

		foreach ( $lines as $line ) {
			// Extracted comments
			if ( 0 === strpos( $line, '#.' ) ) {
				$entry['comments'][] = $line;
				continue;
			}
			// References
			if ( 0 === strpos( $line, '#:' ) ) {
				$entry['references'][] = $line;
				continue;
			}
			// Flags
			if ( 0 === strpos( $line, '#,' ) ) {
				$entry['flags'][] = $line;
				if ( false !== strpos( $line, 'fuzzy' ) ) {
					$entry['is_fuzzy'] = true;
				}
				continue;
			}
			// Translator comments or other # lines
			if ( 0 === strpos( $line, '#' ) ) {
				$entry['comments'][] = $line;
				continue;
			}

			// msgctxt
			if ( 0 === strpos( $line, 'msgctxt ' ) ) {
				$current          = 'msgctxt';
				$entry['msgctxt'] = self::unquote( substr( $line, 8 ) );
				continue;
			}
			// msgid
			if ( 0 === strpos( $line, 'msgid ' ) ) {
				$current        = 'msgid';
				$entry['msgid'] = self::unquote( substr( $line, 6 ) );
				continue;
			}
			// msgid_plural
			if ( 0 === strpos( $line, 'msgid_plural ' ) ) {
				$current               = 'msgid_plural';
				$entry['msgid_plural'] = self::unquote( substr( $line, 13 ) );
				continue;
			}
			// msgstr[n]
			if ( preg_match( '/^msgstr\[(\d+)\] (.*)/', $line, $m ) ) {
				$idx                            = (int) $m[1];
				$current                        = 'msgstr_plural_' . $idx;
				$entry['msgstr_plural'][ $idx ] = self::unquote( $m[2] );
				continue;
			}
			// msgstr
			if ( 0 === strpos( $line, 'msgstr ' ) ) {
				$current         = 'msgstr';
				$entry['msgstr'] = self::unquote( substr( $line, 7 ) );
				continue;
			}
			// Continuation line
			if ( 0 === strpos( $line, '"' ) && $current ) {
				$chunk = self::unquote( $line );
				if ( 'msgid' === $current ) {
					$entry['msgid'] .= $chunk;
				} elseif ( 'msgstr' === $current ) {
					$entry['msgstr'] .= $chunk;
				} elseif ( 'msgctxt' === $current ) {
					$entry['msgctxt'] .= $chunk;
				} elseif ( 'msgid_plural' === $current ) {
					$entry['msgid_plural'] .= $chunk;
				} elseif ( 0 === strpos( $current, 'msgstr_plural_' ) ) {
					$idx                            = (int) substr( $current, 14 );
					$entry['msgstr_plural'][ $idx ] = ( $entry['msgstr_plural'][ $idx ] ?? '' ) . $chunk;
				}
			}
		}

		// Header entry has empty msgid
		if ( '' === $entry['msgid'] && ! empty( $entry['msgstr'] ) ) {
			$entry['is_header'] = true;
		}

		// Skip if no msgid at all (empty block)
		if ( '' === $entry['msgid'] && ! $entry['is_header'] ) {
			return null;
		}

		return $entry;
	}

	/**
	 * Връща непреведени записи, съобразно msgctxt и дедупликацията.
	 * При $skip_translated = true, частичните множествени преводи се третират като непреведени (Strategy B).
	 *
	 * @param  array $entries         Парсирани записи.
	 * @param  bool  $skip_translated Дали да пропуска вече преведените записи.
	 * @return array [ 'index' => int, 'msgid' => string, 'plural' => ?string, 'msgctxt' => ?string, 'duplicates' => int[] ]
	 */
	public static function get_untranslated( array $entries, bool $skip_translated = true ) {
		$result   = [];
		$seen     = [];
		$nplurals = self::get_nplurals( $entries );

		foreach ( $entries as $i => $entry ) {
			if ( $entry['is_header'] ) {
				continue;
			}

			$has_translation = false;
			if ( null !== $entry['msgid_plural'] ) {
				// Плурален запис: изисква точно $nplurals пълни преведени форми
				$plural_forms = $entry['msgstr_plural'] ?? [];
				$filled_count = 0;
				for ( $k = 0; $k < $nplurals; $k++ ) {
					if ( isset( $plural_forms[ $k ] ) && '' !== trim( $plural_forms[ $k ] ) ) {
						$filled_count++;
					}
				}
				$has_translation = ( $filled_count === $nplurals );
			} else {
				$has_translation = ( '' !== trim( $entry['msgstr'] ) );
			}

			$is_fuzzy = $entry['is_fuzzy'];

			if ( $skip_translated && $has_translation && ! $is_fuzzy ) {
				continue;
			}

			// Дедупликация чрез wp_json_encode на [msgctxt, msgid, msgid_plural]
			$key = wp_json_encode( [
				$entry['msgctxt'] ?? '',
				$entry['msgid'],
				$entry['msgid_plural'] ?? '',
			] );

			if ( isset( $seen[ $key ] ) ) {
				$idx                            = $seen[ $key ];
				$result[ $idx ]['duplicates'][] = $i;
			} else {
				$seen[ $key ] = count( $result );
				$result[]     = [
					'index'      => $i,
					'msgid'      => $entry['msgid'],
					'plural'     => $entry['msgid_plural'],
					'msgctxt'    => $entry['msgctxt'],
					'duplicates' => [],
				];
			}
		}

		return $result;
	}

	/**
	 * Извлича локала от PO хедъра или името на файла.
	 *
	 * @param  array  $entries   Парсирани записи.
	 * @param  string $file_path Път до файла.
	 * @return string
	 */
	public static function get_locale( array $entries, string $file_path = '' ) {
		// 1. PO хедър: Language: bg_BG
		foreach ( $entries as $entry ) {
			if ( $entry['is_header'] ) {
				if ( preg_match( '/Language:\s*([a-z]{2,3}(?:_[A-Z]{2,3})?(?:_[a-zA-Z0-9]+)?)/i', $entry['msgstr'], $m ) ) {
					return trim( $m[1] );
				}
			}
		}

		// 2. Име на файла
		if ( $file_path ) {
			$basename = pathinfo( $file_path, PATHINFO_FILENAME );
			if ( preg_match( '/[-_]([a-z]{2,3}(?:_[A-Z]{2,3})?(?:_[a-zA-Z0-9]+)?)$/', $basename, $m ) ) {
				return $m[1];
			}
		}

		return '';
	}

	/**
	 * Връща броя множествени форми от Plural-Forms хедъра или локала.
	 *
	 * @param  array  $entries Parsed entries.
	 * @param  string $locale  Локал.
	 * @return int
	 */
	public static function get_nplurals( array $entries, string $locale = '' ) {
		// 1. Plural-Forms хедър
		foreach ( $entries as $entry ) {
			if ( $entry['is_header'] ) {
				if ( preg_match( '/Plural-Forms:\s*nplurals=(\d+)/i', $entry['msgstr'], $matches ) ) {
					return (int) $matches[1];
				}
			}
		}

		// 2. Локален fallback
		if ( ! $locale ) {
			$locale = self::get_locale( $entries );
		}

		if ( $locale ) {
			$lang = strtolower( strtok( $locale, '_-' ) );
			// 1 форма: китайски, японски, корейски, виетнамски, тайландски
			if ( in_array( $lang, [ 'zh', 'ja', 'ko', 'vi', 'th' ], true ) ) {
				return 1;
			}
			// 3 форми: славянски езици (български, руски, украински, полски, чешки, словашки, сръбски, хърватски)
			if ( in_array( $lang, [ 'bg', 'ru', 'uk', 'pl', 'cs', 'sk', 'bs', 'hr', 'sr' ], true ) ) {
				return 3;
			}
			// 6 форми: арабски
			if ( 'ar' === $lang ) {
				return 6;
			}
		}

		return 2; // По подразбиране
	}

	/**
	 * Прилага преводите върху масива от записи.
	 *
	 * @param  array $entries      Парсирани записи.
	 * @param  array $translations [ index => translated_string|array ]
	 * @return array Обновени записи.
	 */
	public static function apply_translations( array $entries, array $translations ) {
		foreach ( $translations as $idx => $translated ) {
			if ( ! isset( $entries[ $idx ] ) ) {
				continue;
			}

			if ( is_array( $translated ) ) {
				$nplurals = self::get_nplurals( $entries );
				$existing = [];
				for ( $k = 0; $k < $nplurals; $k++ ) {
					$existing[ $k ] = isset( $translated[ $k ] ) ? (string) $translated[ $k ] : '';
				}
				$entries[ $idx ]['msgstr_plural'] = $existing;
				$entries[ $idx ]['msgstr']        = '';
			} else {
				$entries[ $idx ]['msgstr'] = (string) $translated;
			}

			$entries[ $idx ]['is_fuzzy'] = false;

			// Премахване на fuzzy флага при наличен валиден превод
			$entries[ $idx ]['flags'] = array_values( array_filter(
				$entries[ $idx ]['flags'],
				function( $f ) {
					return false === strpos( $f, 'fuzzy' );
				}
			) );
		}

		return $entries;
	}

	/**
	 * Сериализира записите обратно до .po съдържание.
	 *
	 * @param  array $entries
	 * @return string
	 */
	public static function serialize( array $entries ) {
		$lines = [];

		foreach ( $entries as $entry ) {
			$block = [];

			// Коментари
			foreach ( $entry['comments'] as $c ) {
				$block[] = $c;
			}
			foreach ( $entry['references'] as $r ) {
				$block[] = $r;
			}
			foreach ( $entry['flags'] as $f ) {
				$block[] = $f;
			}

			// msgctxt
			if ( null !== $entry['msgctxt'] ) {
				$block[] = 'msgctxt ' . self::quote( $entry['msgctxt'] );
			}

			// msgid
			$block[] = 'msgid ' . self::quote( $entry['msgid'] );

			// msgid_plural
			if ( null !== $entry['msgid_plural'] ) {
				$block[] = 'msgid_plural ' . self::quote( $entry['msgid_plural'] );
				$plurals = $entry['msgstr_plural'] ?: [ 0 => '', 1 => '' ];
				foreach ( $plurals as $i => $val ) {
					$block[] = 'msgstr[' . $i . '] ' . self::quote( $val );
				}
			} else {
				$block[] = 'msgstr ' . self::quote( $entry['msgstr'] );
			}

			$lines[] = implode( "\n", $block );
		}

		return implode( "\n\n", $lines ) . "\n";
	}

	/**
	 * Записва обновените записи атомарно в .po файла и компилира .mo.
	 *
	 * @param  string $po_path Absolute path to .po file.
	 * @param  array  $entries Updated entries.
	 * @return true|\WP_Error
	 */
	public static function save( string $po_path, array $entries ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) && function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$dir = dirname( $po_path );
		$is_dir_writable = $wp_filesystem ? $wp_filesystem->is_writable( $dir ) : is_writable( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( ! is_dir( $dir ) || ! $is_dir_writable ) {
			return new \WP_Error(
				'not_writable',
				sprintf(
					/* translators: %s: directory path */
					__( 'PO file directory is not writable: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$dir
				)
			);
		}

		$is_file_writable = $wp_filesystem ? $wp_filesystem->is_writable( $po_path ) : is_writable( $po_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( file_exists( $po_path ) && ! $is_file_writable ) {
			return new \WP_Error(
				'not_writable',
				sprintf(
					/* translators: %s: file path */
					__( 'PO file is not writable: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$po_path
				)
			);
		}

		$content  = self::serialize( $entries );
		$tmp_path = $po_path . '.tmp.' . uniqid( '', true );

		// Direct filesystem operation with LOCK_EX is intentional here to ensure exclusive atomic file locking for PO generation before atomic move/rename.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $tmp_path, $content, LOCK_EX );
		if ( false === $written || $written !== strlen( $content ) ) {
			if ( file_exists( $tmp_path ) ) {
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $tmp_path );
				} else {
					@unlink( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
			return new \WP_Error(
				'write_error',
				__( 'Error writing temporary PO file.', 'ewa-ai-string-assistant-for-loco-translate' )
			);
		}

		if ( file_exists( $po_path ) ) {
			$perms = @fileperms( $po_path );
			if ( false !== $perms ) {
				if ( $wp_filesystem ) {
					$wp_filesystem->chmod( $tmp_path, $perms & 0777 );
				} else {
					@chmod( $tmp_path, $perms & 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
				}
			}
		}

		$renamed = false;
		if ( $wp_filesystem ) {
			$renamed = $wp_filesystem->move( $tmp_path, $po_path, true );
		}
		if ( ! $renamed ) {
			$renamed = @rename( $tmp_path, $po_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		}

		if ( ! $renamed ) {
			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $tmp_path );
			} else {
				@unlink( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return new \WP_Error(
				'atomic_save_failed',
				__( 'Could not atomically replace the PO file.', 'ewa-ai-string-assistant-for-loco-translate' )
			);
		}

		$mo_result = self::compile_mo( $po_path );
		if ( is_wp_error( $mo_result ) ) {
			return new \WP_Error(
				'mo_compile_warning',
				sprintf(
					/* translators: %s: MO compilation error message */
					__( 'PO file was saved successfully, but MO file compilation failed: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$mo_result->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Компилира .po до .mo файл.
	 *
	 * @param  string $po_path
	 * @return true|\WP_Error
	 */
	private static function compile_mo( string $po_path ) {
		$mo_path = preg_replace( '/\.po$/i', '.mo', $po_path );
		return self::write_mo_fallback( $po_path, $mo_path );
	}

	/**
	 * Пише MO файл чрез вградените WordPress POMO класове.
	 *
	 * @param  string $po_path
	 * @param  string $mo_path
	 * @return true|\WP_Error
	 */
	private static function write_mo_fallback( string $po_path, string $mo_path ) {
		if ( ! class_exists( '\PO' ) && ! class_exists( 'PO' ) ) {
			if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-includes/pomo/po.php' ) ) {
				require_once ABSPATH . 'wp-includes/pomo/po.php';
				require_once ABSPATH . 'wp-includes/pomo/mo.php';
			} else {
				return true; // В независима тестова среда
			}
		}

		if ( class_exists( '\PO' ) ) {
			$po = new \PO();
			if ( $po->import_from_file( $po_path ) ) {
				$mo          = new \MO();
				$mo->entries = $po->entries;
				$mo->set_headers( $po->headers );
				$exported = $mo->export_to_file( $mo_path );
				if ( ! $exported ) {
					return new \WP_Error(
						'mo_export_failed',
						sprintf(
							/* translators: %s: MO file path */
							__( 'Could not write MO file: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
							$mo_path
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Проверява дали низът не подлежи на превод.
	 *
	 * @param  string $str
	 * @return bool
	 */
	public static function is_non_translatable( string $str ) {
		$str = trim( $str );
		if ( '' === $str ) {
			return true;
		}

		if ( is_numeric( $str ) ) {
			return true;
		}

		$placeholder_pattern = '/^(?:%[0-9]*\$?[sd]|{{?[a-zA-Z0-9_\-\s]+}?})+$/';
		if ( preg_match( $placeholder_pattern, $str ) ) {
			return true;
		}

		if ( preg_match( '/^https?:\/\/[^\s]+$/i', $str ) ) {
			return true;
		}

		$stripped = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $str ) : strip_tags( $str ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		$stripped = html_entity_decode( $stripped );
		$stripped = preg_replace( '/&[a-zA-Z0-9#]+;/', '', $stripped );
		if ( ! preg_match( '/[a-zA-Z\p{L}0-9]/u', $stripped ) ) {
			return true;
		}

		return false;
	}

	private static function unquote( string $str ) {
		$str = trim( $str );
		if ( 0 === strpos( $str, '"' ) && '"' === substr( $str, -1 ) ) {
			$str = substr( $str, 1, -1 );
		}
		return stripcslashes( $str );
	}

	private static function quote( string $str ) {
		return '"' . addcslashes( $str, "\0\\\"\n\r\t" ) . '"';
	}
}
