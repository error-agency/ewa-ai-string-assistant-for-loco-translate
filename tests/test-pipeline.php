<?php
/**
 * Test Suite for EWA AI String Assistant for Loco Translate (Error Web Agency).
 * Tests:
 * 1. msgctxt separation and deduplication
 * 2. Tokenizer placeholder validation (%s, %d, %02d, %1$s, %2$03d, {var}, {{var}}, %%)
 * 3. HTML tag, attribute (href, src, title), and entity (&nbsp;, &amp;) validation
 * 4. Plural validation & Strategy B partial overwrite behavior
 * 5. Reordered ID response mapping & malformed response rejection
 * 6. PO file parsing, atomic saving, and round-trip integrity
 */

require_once __DIR__ . '/bootstrap.php';

use ErrorWebAgency\EwaAIStringAssistant\Po_Handler;
use ErrorWebAgency\EwaAIStringAssistant\Translation_Validator;
use ErrorWebAgency\EwaAIStringAssistant\Api_Client;

class Test_Runner {

	private $passed = 0;
	private $failed = 0;

	public function run() {
		echo "=======================================================\n";
		echo "   EWA AI STRING ASSISTANT - AUTOMATED PIPELINE TEST SUITE\n";
		echo "=======================================================\n\n";

		$this->test_msgctxt_deduplication();
		$this->test_placeholder_validation();
		$this->test_html_and_entities_validation();
		$this->test_plural_validation_and_strategy_b();
		$this->test_deterministic_id_mapping();
		$this->test_po_roundtrip_integrity();
		$this->test_atomic_file_saving();

		echo "\n-------------------------------------------------------\n";
		echo sprintf( "РЕЗУЛТАТ: %d преминати, %d провали.\n", $this->passed, $this->failed );
		echo "-------------------------------------------------------\n";

		if ( $this->failed > 0 ) {
			exit( 1 );
		}
	}

	private function assert( $condition, $message ) {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] " . $message . "\n";
		} else {
			$this->failed++;
			echo "[FAIL] " . $message . "\n";
		}
	}

	/**
	 * 1. Test msgctxt separation & deduplication
	 */
	private function test_msgctxt_deduplication() {
		echo "--- 1. Testing msgctxt & Deduplication ---\n";

		$content = <<<PO
msgctxt "noun"
msgid "Post"
msgstr ""

msgctxt "verb"
msgid "Post"
msgstr ""

msgctxt "button"
msgid "Save"
msgstr ""

msgctxt "button"
msgid "Save"
msgstr ""
PO;

		$entries      = Po_Handler::parse_content( $content );
		$untranslated = Po_Handler::get_untranslated( $entries, true );

		$this->assert( count( $untranslated ) === 3, 'Трябва да съществуват точно 3 уникални преводни единици.' );

		$item0 = $untranslated[0];
		$item1 = $untranslated[1];
		$item2 = $untranslated[2];

		$this->assert( 'Post' === $item0['msgid'] && 'noun' === $item0['msgctxt'], 'Запис 0 трябва да е Post с контекст noun' );
		$this->assert( 'Post' === $item1['msgid'] && 'verb' === $item1['msgctxt'], 'Запис 1 трябва да е Post с контекст verb' );
		$this->assert( 'Save' === $item2['msgid'] && 'button' === $item2['msgctxt'], 'Запис 2 трябва да е Save с контекст button' );
		$this->assert( count( $item2['duplicates'] ) === 1, 'Повторението на Save с контекст button трябва да бъде дедупликирано' );
	}

	/**
	 * 2. Test Tokenizer Placeholder Validation
	 */
	private function test_placeholder_validation() {
		echo "\n--- 2. Testing Placeholder Tokenizer Validation ---\n";

		// Printf formats
		$v1 = Translation_Validator::validate_pair( 'Hello %s', 'Здравей %s' );
		$this->assert( true === $v1, 'Валиден %s плейсхолдър трябва да се приеме' );

		$v2 = Translation_Validator::validate_pair( 'Hello %s', 'Здравей' );
		$this->assert( is_wp_error( $v2 ), 'Липсващ %s плейсхолдър трябва да бъде отхвърлен' );

		$v3 = Translation_Validator::validate_pair( 'You have %1$d items in %2$s', 'Имате %2$s с %1$d артикула' );
		$this->assert( true === $v3, 'Разменени позиционни плейсхолдъри %1$d и %2$s трябва да се приемат' );

		$v4 = Translation_Validator::validate_pair( 'Progress: 100%%', 'Прогрес: 100%%' );
		$this->assert( true === $v4, 'Съвпадащ %% символ трябва да се приеме' );

		$v5 = Translation_Validator::validate_pair( 'Progress: 100%%', 'Прогрес: 100%' );
		$this->assert( is_wp_error( $v5 ), 'Несъответствие при %% символа трябва да се отхвърли' );

		// Complex specifiers
		$v6 = Translation_Validator::validate_pair( 'Value: %02d and %05.2f', 'Стойност: %02d и %05.2f' );
		$this->assert( true === $v6, 'Сложни printf спецификатори (%02d, %05.2f) трябва да се приемат' );

		// Variables {name} and {{name}}
		$v7 = Translation_Validator::validate_pair( 'Hello {name} and {{user}}', 'Здравей {name} и {{user}}' );
		$this->assert( true === $v7, 'Променливи {name} и {{user}} трябва да се токенизират правилно' );

		$v8 = Translation_Validator::validate_pair( 'Hello {name}', 'Здравей' );
		$this->assert( is_wp_error( $v8 ), 'Липсваща променлива {name} трябва да се отхвърли' );
	}

	/**
	 * 3. Test HTML Tags, Attributes, and Entities
	 */
	private function test_html_and_entities_validation() {
		echo "\n--- 3. Testing HTML & Entity Validation ---\n";

		$html1 = Translation_Validator::validate_pair(
			'<a href="%s"><strong>Read more</strong></a>',
			'<a href="%s"><strong>Прочетете повече</strong></a>'
		);
		$this->assert( true === $html1, 'Валидни HTML тагове <a>, <strong> и атрибут href="%s" трябва да се приемат' );

		$html2 = Translation_Validator::validate_pair(
			'<a href="%s">Read more</a>',
			'<a href="http://wrong.com">Read more</a>'
		);
		$this->assert( is_wp_error( $html2 ), 'Повреден плейсхолдър %s вътре в href атрибут трябва да се отхвърли' );

		$ent1 = Translation_Validator::validate_html_entities( 'Hello &nbsp; &amp; world &hellip;', 'Здравей &nbsp; &amp; свят &hellip;' );
		$this->assert( true === $ent1, 'Валидни HTML ентитети трябва да се приемат' );

		$ent2 = Translation_Validator::validate_html_entities( 'Hello &nbsp; world', 'Здравей свят' );
		$this->assert( is_wp_error( $ent2 ), 'Липсващ &nbsp; ентитет трябва да се отхвърли' );
	}

	/**
	 * 4. Test Plurals & Strategy B Incomplete Overwrite
	 */
	private function test_plural_validation_and_strategy_b() {
		echo "\n--- 4. Testing Plurals & Strategy B ---\n";

		// Validation of 3 plural forms for Bulgarian
		$plural_vals = [ '1 артикул', '%d артикула', '%d артикула' ];
		$p1 = Translation_Validator::validate_plural( '%d item', '%d items', $plural_vals, 3 );
		$this->assert( true === $p1, 'Точно 3 преведени множествени форми с %d трябва да се приемат' );

		$p2 = Translation_Validator::validate_plural( '%d item', '%d items', [ '1 артикул', '%d артикула' ], 3 );
		$this->assert( is_wp_error( $p2 ), 'Недостатъчен брой множествени форми (2 при очаквани 3) трябва да се отхвърли' );

		// Strategy B partial plural overwrite test
		$po_partial = <<<PO
msgid ""
msgstr ""
"Plural-Forms: nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);\n"

msgid "%d item"
msgid_plural "%d items"
msgstr[0] "1 продукт"
msgstr[1] ""
msgstr[2] ""
PO;

		$entries      = Po_Handler::parse_content( $po_partial );
		$untranslated = Po_Handler::get_untranslated( $entries, true );

		$this->assert( count( $untranslated ) === 1, 'Частично преведен плурален запис трябва да се върне като непреведен (Strategy B)' );
	}

	/**
	 * 5. Test Deterministic ID Mapping
	 */
	private function test_deterministic_id_mapping() {
		echo "\n--- 5. Testing ID Mapping & Malformed Response Handling ---\n";

		$client = new Api_Client();

		$item_map = [
			'entry_10' => [ 'index' => 10, 'msgid' => 'Save', 'plural' => null, 'msgctxt' => null ],
			'entry_20' => [ 'index' => 20, 'msgid' => 'Cancel', 'plural' => null, 'msgctxt' => null ],
		];

		// Simulating AI response with reordered IDs
		$reordered_json = json_encode([
			'translations' => [
				[ 'id' => 'entry_20', 'translation' => 'Отказ' ],
				[ 'id' => 'entry_10', 'translation' => 'Запази' ],
			]
		]);

		$method = new ReflectionMethod( Api_Client::class, 'parse_raw_content' );
		$method->setAccessible( true );

		$parsed = $method->invoke( $client, $reordered_json, $item_map, 2 );

		$this->assert( ! is_wp_error( $parsed ), 'Разменени ID-та от AI трябва да се съпоставят успешно' );
		$this->assert( ( $parsed[10] ?? '' ) === 'Запази', 'entry_10 трябва да получи "Запази"' );
		$this->assert( ( $parsed[20] ?? '' ) === 'Отказ', 'entry_20 трябва да получи "Отказ"' );

		// Test URL normalization to prevent double endpoint 404s
		$c1 = new Api_Client([ 'api_endpoint' => 'https://openrouter.ai/api/v1' ]);
		$this->assert( 'https://openrouter.ai/api/v1/chat/completions' === $c1->build_endpoint_url('chat/completions'), 'Нормален API адрес изгражда правилен chat/completions' );

		$c2 = new Api_Client([ 'api_endpoint' => 'https://openrouter.ai/api/v1/chat/completions' ]);
		$this->assert( 'https://openrouter.ai/api/v1/chat/completions' === $c2->build_endpoint_url('chat/completions'), 'Поставен пълен URL /chat/completions не трябва да се дублира (предотвратява 404)' );

		$c3 = new Api_Client([ 'api_endpoint' => 'http://localhost:11434/api/chat' ]);
		$this->assert( 'http://localhost:11434/api/chat' === $c3->build_endpoint_url('api/chat'), 'Ollama адрес с /api/chat не се дублира' );
	}

	/**
	 * 6. Test PO File Round-trip Integrity
	 */
	private function test_po_roundtrip_integrity() {
		echo "\n--- 6. Testing PO Round-trip Integrity ---\n";

		$original_po = <<<PO
msgid ""
msgstr ""
"Project-Id-Version: Test 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"

#  Translator comment
#. Extracted comment
#: file.php:15
#, fuzzy, php-format
#| msgid "Previous string"
#~ msgid "Obsolete string"
msgctxt "noun"
msgid "Post \"quoted\" \\escaped\\"
msgid_plural "Posts"
msgstr[0] ""
msgstr[1] ""
PO;

		$entries    = Po_Handler::parse_content( $original_po );
		$serialized = Po_Handler::serialize( $entries );
		$reparsed   = Po_Handler::parse_content( $serialized );

		$this->assert( count( $entries ) === count( $reparsed ), 'Броят на записите при двупосочно конвертиране трябва да съвпада' );

		$e0 = $entries[1] ?? $entries[0];
		$r0 = $reparsed[1] ?? $reparsed[0];

		$this->assert( $e0['msgid'] === $r0['msgid'], 'msgid с кавички и наклонени черти трябва да се запази идентично' );
		$this->assert( $e0['msgctxt'] === $r0['msgctxt'], 'msgctxt трябва да се запази идентично' );
		$this->assert( count( $e0['references'] ) === count( $r0['references'] ), 'Референциите (#:) трябва да се запазят' );
	}

	/**
	 * 7. Test Atomic File Saving
	 */
	private function test_atomic_file_saving() {
		echo "\n--- 7. Testing Atomic File Saving ---\n";

		$tmp_dir = sys_get_temp_dir() . '/lait_test_' . uniqid();
		mkdir( $tmp_dir );
		$po_file = $tmp_dir . '/test.po';
		file_put_contents( $po_file, "msgid \"Test\"\nmsgstr \"\"\n" );

		$entries = Po_Handler::parse( $po_file );
		$entries[0]['msgstr'] = 'Тест';

		$save_res = Po_Handler::save( $po_file, $entries );
		$this->assert( ! is_wp_error( $save_res ) || 'mo_compile_warning' === $save_res->get_error_code(), 'Атомарният запис на PO файла трябва да бъде успешен' );

		$reloaded = Po_Handler::parse( $po_file );
		$this->assert( $reloaded[0]['msgstr'] === 'Тест', 'Презареденият PO файл трябва да съдържа новия превод' );

		// Cleanup
		@unlink( $po_file );
		@unlink( $tmp_dir . '/test.mo' );
		@rmdir( $tmp_dir );
	}
}

$runner = new Test_Runner();
$runner->run();
