<?php
namespace Opencart\System\Library\Cckc;

/**
 * Sync-journal storage. One row per order_id; the UNIQUE key makes syncing
 * idempotent — a completed row short-circuits, so an order is never pushed to
 * KeepinCRM twice even if the event fires again.
 */
class Logger {
	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	public static function table(): string { return DB_PREFIX . 'cc_keepincrm_sync'; }

	public function find(int $orderId): ?array {
		$row = $this->db->query("SELECT * FROM `" . self::table() . "` WHERE order_id = " . (int)$orderId . " LIMIT 1")->row;
		return $row ?: null;
	}

	public function isCompleted(int $orderId): bool {
		$row = $this->find($orderId);
		return $row !== null && ($row['status'] ?? '') === 'completed';
	}

	/** Insert a pending row (or reset an existing non-completed one). Returns row id. */
	public function begin(int $orderId): int {
		$existing = $this->find($orderId);
		if ($existing) {
			if (($existing['status'] ?? '') === 'completed') {
				return (int)$existing['id'];
			}
			$this->db->query("UPDATE `" . self::table() . "` SET status = 'pending', updated_at = NOW() WHERE id = " . (int)$existing['id']);
			return (int)$existing['id'];
		}
		$this->db->query("INSERT INTO `" . self::table() . "` SET
			order_id = " . (int)$orderId . ",
			status = 'pending',
			attempts = 0,
			created_at = NOW(),
			updated_at = NOW()");
		return (int)$this->db->getLastId();
	}

	public function complete(int $id, string $externalId, string $request, string $response): void {
		$this->db->query("UPDATE `" . self::table() . "` SET
			status = 'completed',
			external_id = '" . $this->db->escape($externalId) . "',
			last_error = '',
			attempts = attempts + 1,
			request_excerpt = '" . $this->db->escape(mb_substr($request, 0, 4000)) . "',
			response_excerpt = '" . $this->db->escape(mb_substr($response, 0, 4000)) . "',
			updated_at = NOW()
			WHERE id = " . (int)$id);
	}

	public function fail(int $id, string $error, string $request, string $response): void {
		$this->db->query("UPDATE `" . self::table() . "` SET
			status = 'failed',
			last_error = '" . $this->db->escape(mb_substr($error, 0, 1000)) . "',
			attempts = attempts + 1,
			request_excerpt = '" . $this->db->escape(mb_substr($request, 0, 4000)) . "',
			response_excerpt = '" . $this->db->escape(mb_substr($response, 0, 4000)) . "',
			updated_at = NOW()
			WHERE id = " . (int)$id);
	}

	/** Failed rows still under the attempt cap, for the retry cron. */
	public function retryable(int $maxAttempts, int $limit = 50): array {
		return $this->db->query("SELECT * FROM `" . self::table() . "`
			WHERE status = 'failed' AND attempts < " . (int)$maxAttempts . "
			ORDER BY updated_at ASC LIMIT " . (int)$limit)->rows;
	}

	public function recent(int $limit = 100): array {
		return $this->db->query("SELECT * FROM `" . self::table() . "` ORDER BY id DESC LIMIT " . (int)$limit)->rows;
	}
}
