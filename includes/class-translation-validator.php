<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Валидатор за преводна интегритетност.
 * Проверява запазването на технически токени, printf плейсхолдъри, променливи,
 * HTML тагове, атрибути, ентитети и брой множествени форми.
 */
class Translation_Validator {

	/**
	 * Валидира единична двойка изходен текст -> превод.
	 *
	 * @param  string $source      Изходен низ.
	 * @param  string $translation Преведен низ.
	 * @return true|\WP_Error
	 */
	public static function validate_pair( string $source, string $translation ) {
		// 1. Валидация на printf и променливи плейсхолдъри
		$placeholders_check = self::validate_placeholders( $source, $translation );
		if ( is_wp_error( $placeholders_check ) ) {
			return $placeholders_check;
		}

		// 2. Валидация на HTML тагове и атрибути
		$html_check = self::validate_html( $source, $translation );
		if ( is_wp_error( $html_check ) ) {
			return $html_check;
		}

		// 3. Валидация на HTML ентитети
		$entities_check = self::validate_html_entities( $source, $translation );
		if ( is_wp_error( $entities_check ) ) {
			return $entities_check;
		}

		return true;
	}

	/**
	 * Валидира множествени форми спрямо очаквания nplurals.
	 *
	 * @param  string $singular     Единствено число изходен низ.
	 * @param  string $plural       Множествено число изходен низ.
	 * @param  array  $translations Масив от преведени форми.
	 * @param  int    $nplurals     Очакван брой множествени форми.
	 * @return true|\WP_Error
	 */
	public static function validate_plural( string $singular, string $plural, array $translations, int $nplurals ) {
		if ( count( $translations ) !== $nplurals ) {
			return new \WP_Error(
				'invalid_plural_count',
				sprintf( 'Очаквани бяха %d множествени форми, но бяха получени %d.', $nplurals, count( $translations ) )
			);
		}

		foreach ( $translations as $idx => $form ) {
			if ( ! is_string( $form ) || '' === trim( $form ) ) {
				return new \WP_Error(
					'empty_plural_form',
					sprintf( 'Форма [%d] от множествения превод е празна.', $idx )
				);
			}

			$check_plural   = self::validate_pair( $plural, $form );
			$check_singular = self::validate_pair( $singular, $form );

			if ( ! is_wp_error( $check_plural ) || ! is_wp_error( $check_singular ) ) {
				continue;
			}

			// За форма 0 (единствено число), %d може да е заместен с буквален числител ("1 продукт" вместо "%d продукта")
			if ( 0 === $idx ) {
				$singular_nod = preg_replace( '/%(?:[1-9]\d*\$)?d/', '1', $singular );
				$check_nod    = self::validate_pair( $singular_nod, $form );
				if ( ! is_wp_error( $check_nod ) ) {
					continue;
				}
			}

			return new \WP_Error(
				'plural_placeholder_mismatch',
				sprintf( 'Форма [%d] губи плейсхолдъри: %s', $idx, $check_plural->get_error_message() )
			);
		}

		return true;
	}

	/**
	 * Токенизира и сравнява плейсхолдъри (%s, %d, %1$s, {name}, {{name}}).
	 *
	 * @param  string $source
	 * @param  string $translation
	 * @return true|\WP_Error
	 */
	public static function validate_placeholders( string $source, string $translation ) {
		$src_tokens = self::extract_placeholders( $source );
		$dst_tokens = self::extract_placeholders( $translation );

		// 1. Проверка за %% (literal percent)
		if ( substr_count( $source, '%%' ) !== substr_count( $translation, '%%' ) ) {
			return new \WP_Error( 'percent_mismatch', 'Несъответствие в броя на символите % (%%).' );
		}

		// 2. Сравнение на позиционни и обикновени плейсхолдъри
		$src_counts = array_count_values( $src_tokens );
		$dst_counts = array_count_values( $dst_tokens );

		foreach ( $src_counts as $token => $count ) {
			$found = $dst_counts[ $token ] ?? 0;
			if ( $found !== $count ) {
				return new \WP_Error(
					'missing_placeholder',
					sprintf( 'Плейсхолдърът "%s" липсва или е променен в превода (очаквани: %d, намерени: %d).', $token, $count, $found )
				);
			}
		}

		// Проверка за неочаквано добавени нови плейсхолдъри
		foreach ( $dst_counts as $token => $count ) {
			if ( ! isset( $src_counts[ $token ] ) ) {
				return new \WP_Error(
					'unexpected_placeholder',
					sprintf( 'Открит е неочакван нов плейсхолдър "%s" в превода.', $token )
				);
			}
		}

		return true;
	}

	/**
	 * Извлича плейсхолдъри чрез токенизация.
	 *
	 * @param  string $text
	 * @return array
	 */
	public static function extract_placeholders( string $text ) {
		$tokens = [];

		// Първо извличаме {{var}} за да не се дублира с {var}
		preg_match_all( '/\{\{[a-zA-Z0-9_\-\s]+\}\}/u', $text, $matches_double );
		if ( ! empty( $matches_double[0] ) ) {
			foreach ( $matches_double[0] as $m ) {
				$tokens[] = $m;
			}
			$text = str_replace( $matches_double[0], '', $text );
		}

		// Извличаме единични {var}
		preg_match_all( '/\{[a-zA-Z0-9_\-\s]+\}/u', $text, $matches_single );
		if ( ! empty( $matches_single[0] ) ) {
			foreach ( $matches_single[0] as $m ) {
				$tokens[] = $m;
			}
		}

		// Пълен printf токенизатор: % [argnum$] [flags] [width] [.precision] specifier
		// specifiers: s, d, f, u, x, X, e, E, g, c
		$printf_pattern = '/%(?:[1-9]\d*\$)?[\+\-\'0\s]?(?:\d+)?(?:\.\d+)?[sdfuxXeEgc]/';
		preg_match_all( $printf_pattern, $text, $matches_printf );
		if ( ! empty( $matches_printf[0] ) ) {
			foreach ( $matches_printf[0] as $m ) {
				$tokens[] = $m;
			}
		}

		return $tokens;
	}

	/**
	 * Валидира съответствието на HTML тагове и атрибути.
	 *
	 * @param  string $source
	 * @param  string $translation
	 * @return true|\WP_Error
	 */
	public static function validate_html( string $source, string $translation ) {
		$src_tags = self::extract_html_tags( $source );
		$dst_tags = self::extract_html_tags( $translation );

		// 1. Проверка на таговете (по имена)
		$src_names = array_count_values( $src_tags['names'] );
		$dst_names = array_count_values( $dst_tags['names'] );

		foreach ( $src_names as $name => $count ) {
			$found = $dst_names[ $name ] ?? 0;
			if ( $found !== $count ) {
				return new \WP_Error(
					'missing_html_tag',
					sprintf( 'HTML тагът "<%s>" липсва или броят му е променен в превода (очаквани: %d, намерени: %d).', $name, $count, $found )
				);
			}
		}

		foreach ( $dst_names as $name => $count ) {
			if ( ! isset( $src_names[ $name ] ) ) {
				return new \WP_Error(
					'unexpected_html_tag',
					sprintf( 'Открит е неочакван нов HTML таг "<%s>" в превода.', $name )
				);
			}
		}

		// 2. Проверка на критични атрибути (href, src, title, alt)
		foreach ( $src_tags['attributes'] as $attr_name => $src_vals ) {
			$dst_vals = $dst_tags['attributes'][ $attr_name ] ?? [];
			if ( count( $src_vals ) !== count( $dst_vals ) ) {
				return new \WP_Error(
					'missing_html_attribute',
					sprintf( 'Броят на атрибутите "%s" в HTML таговете не съвпада.', $attr_name )
				);
			}
			// Проверка дали плейсхолдърите вътре в атрибутите са запазени
			foreach ( $src_vals as $i => $val ) {
				$dst_val = $dst_vals[ $i ] ?? '';
				$val_placeholders = self::extract_placeholders( $val );
				if ( ! empty( $val_placeholders ) ) {
					$val_check = self::validate_placeholders( $val, $dst_val );
					if ( is_wp_error( $val_check ) ) {
						return new \WP_Error(
							'attribute_placeholder_mismatch',
							sprintf( 'Плейсхолдър в атрибут "%s" бе повреден: %s', $attr_name, $val_check->get_error_message() )
						);
					}
				}
			}
		}

		return true;
	}

	/**
	 * Извлича имената на HTML тагове и техните критични атрибути.
	 *
	 * @param  string $text
	 * @return array [ 'names' => [...], 'attributes' => [ 'href' => [...], ... ] ]
	 */
	private static function extract_html_tags( string $text ) {
		$names      = [];
		$attributes = [];
		$critical   = [ 'href', 'src', 'title', 'alt' ];

		preg_match_all( '/<(\/?[a-zA-Z0-9]+)([^>]*)>/u', $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $m ) {
			$raw_name = strtolower( ltrim( $m[1], '/' ) );
			$names[]  = $raw_name;

			$attr_str = $m[2];
			if ( ! empty( $attr_str ) ) {
				foreach ( $critical as $c_attr ) {
					if ( preg_match( '/' . preg_quote( $c_attr, '/' ) . '=(["\'])(.*?)\1/i', $attr_str, $attr_m ) ) {
						$attributes[ $c_attr ][] = $attr_m[2];
					}
				}
			}
		}

		return [
			'names'      => $names,
			'attributes' => $attributes,
		];
	}

	/**
	 * Валидира наличието на ключови HTML ентитети (&nbsp;, &amp;, &hellip;, &#039; и др.).
	 *
	 * @param  string $source
	 * @param  string $translation
	 * @return true|\WP_Error
	 */
	public static function validate_html_entities( string $source, string $translation ) {
		$important_entities = [ '&nbsp;', '&amp;', '&hellip;', '&#039;', '&quot;', '&lt;', '&gt;' ];

		foreach ( $important_entities as $entity ) {
			$src_count = substr_count( $source, $entity );
			if ( $src_count > 0 ) {
				$dst_count = substr_count( $translation, $entity );
				if ( $dst_count !== $src_count ) {
					if ( '&amp;' === $entity && substr_count( $translation, '&' ) >= $src_count ) {
						continue;
					}
					return new \WP_Error(
						'entity_mismatch',
						sprintf( 'HTML ентитетът "%s" бе променен в превода (очаквани: %d, намерени: %d).', $entity, $src_count, $dst_count )
					);
				}
			}
		}

		return true;
	}
}
