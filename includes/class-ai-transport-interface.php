<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Интерфейс за транспортен слой към AI провайдъри.
 * Абстрахира комуникацията с WordPress AI Client и директните HTTP конектори.
 */
interface AI_Transport_Interface {

	/**
	 * Проверява дали транспортът е наличен и готов за генерация на текст.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Изпълнява заявка за генериране на превод.
	 *
	 * @param  string $prompt Потребителски промпт със структурираните за превод елементи.
	 * @param  array  $args   Допълнителни параметри:
	 *                        - 'system_prompt'  (string) Системни инструкции за превод.
	 *                        - 'temperature'    (float)  Температура за генерация.
	 *                        - 'models'         (array)  Предпочитани модели (опционално).
	 *                        - 'target_lang'    (string) Целеви език.
	 * @return array|\WP_Error Масив с ключ 'text' (string) и опционален '_usage' (array), или \WP_Error при грешка.
	 */
	public function generate( string $prompt, array $args = [] );
}
