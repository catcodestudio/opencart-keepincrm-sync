<?php
namespace Opencart\System\Library\Cckc;

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/client.php';

/**
 * Pushes a mapped order to KeepinCRM idempotently and records the attempt in
 * the sync journal. A completed journal row short-circuits, so re-firing the
 * order event never creates a duplicate KeepinCRM заявка.
 */
class Sender {
	private $db;
	private Settings $settings;
	private Logger $logger;

	public function __construct($db, Settings $settings) {
		$this->db       = $db;
		$this->settings = $settings;
		$this->logger   = new Logger($db);
	}

	/**
	 * @param array $payload OrderMapper::build output (flat KeepinCRM body).
	 * @return array{ok:bool,external_id?:string,error?:string,skipped?:bool}
	 */
	public function send(int $orderId, array $payload): array {
		if ($this->logger->isCompleted($orderId)) {
			return ['ok' => true, 'skipped' => true];
		}

		$id     = $this->logger->begin($orderId);
		$client = new Client((string)$this->settings->get('api_key', ''));

		try {
			$res = $client->createAgreement($payload);
		} catch (\Throwable $e) {
			$this->logger->fail($id, $e->getMessage(), '', '');
			return ['ok' => false, 'error' => $e->getMessage()];
		}

		$reqJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		// KeepinCRM returns 201 with {"id":N,...}.
		$sdId = 0;
		if (!empty($res['ok']) && is_array($res['json'])) {
			$sdId = (int)($res['json']['id'] ?? 0);
		}

		if (!empty($res['ok']) && $sdId > 0) {
			$this->logger->complete($id, (string)$sdId, (string)$reqJson, (string)$res['body']);
			return ['ok' => true, 'external_id' => (string)$sdId];
		}

		$err = $res['error'] !== '' ? $res['error'] : 'No agreement id in response';
		$this->logger->fail($id, $err, (string)$reqJson, (string)$res['body']);
		return ['ok' => false, 'error' => $err];
	}
}
