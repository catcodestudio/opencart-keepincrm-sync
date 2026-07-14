<?php
namespace Opencart\System\Library\Cckc;

require_once __DIR__ . '/crypto.php';

/**
 * Settings repository over the OpenCart config. Values live under
 * module_cc_keepincrm_*; the secret api_key is transparently decrypted.
 */
class Settings {
	public const SECRET_KEYS = ['api_key'];

	private $config;
	private ?array $cache = null;

	public function __construct($config) {
		$this->config = $config;
	}

	public static function defaults(): array {
		return [
			'status'              => '0',
			'api_key'             => '',
			'send_on'             => 'create',   // create | status
			'trigger_status'      => '1',        // OC order_status_id when send_on = status
			'skip_zero'           => '1',
			'include_ship'        => '1',
			'retry_enabled'       => '1',
			'max_attempts'        => '5',
			'source_label'        => 'OpenCart',
			'funnel_id'           => '0',
			'stage_id'            => '0',
			'source_id'           => '0',
			'main_responsible_id' => '0',
		];
	}

	public function all(): array {
		if ($this->cache !== null) {
			return $this->cache;
		}
		$out = self::defaults();
		foreach ($out as $key => $default) {
			$val = $this->config->get('module_cc_keepincrm_' . $key);
			if ($val !== null && $val !== '') {
				$out[$key] = $val;
			}
		}
		foreach (self::SECRET_KEYS as $secret) {
			if (!empty($out[$secret])) {
				$out[$secret] = Crypto::decrypt((string)$out[$secret]);
			}
		}
		$this->cache = $out;
		return $out;
	}

	public function get(string $key, $default = null) {
		$all = $this->all();
		return $all[$key] ?? $default;
	}

	public function isConfigured(): bool {
		return (string)$this->get('api_key', '') !== '';
	}
}
