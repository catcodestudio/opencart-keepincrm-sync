<?php
namespace Opencart\System\Library\Cckc;

require_once __DIR__ . '/http.php';

/**
 * KeepinCRM API client.
 *
 * Public REST API — one host, token in a header (no per-account domain):
 *   Base: https://api.keepincrm.com/v1   Auth: header  X-Auth-Token: <token>
 *
 * Order create posts an "agreement": POST /agreements -> 201 { id, ... }.
 * Update:            PATCH /agreements/{id}.
 * Connection test:   GET /clients/statuses.
 *
 * Docs: https://app.swaggerhub.com/apis/KeepInCRM/keepincrm-api/
 */
class Client {
	private const BASE = 'https://api.keepincrm.com/v1';

	private string $token;

	public function __construct(string $token) {
		$this->token = trim($token);
	}

	/**
	 * Create an agreement (order/deal) in KeepinCRM.
	 *
	 * @param array $payload title, total, client_attributes, jobs_attributes...
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function createAgreement(array $payload): array {
		return $this->request('POST', '/agreements', $payload);
	}

	/**
	 * Update an existing agreement by its KeepinCRM id.
	 *
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function updateAgreement(int $id, array $payload): array {
		return $this->request('PATCH', '/agreements/' . $id, $payload);
	}

	/** Cheap authenticated read used by the "test connection" button. */
	public function testConnection(): array {
		return $this->request('GET', '/clients/statuses', null);
	}

	private function request(string $method, string $path, ?array $body = null): array {
		if ($this->token === '') {
			return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'KeepinCRM API token is empty'];
		}

		$res     = Http::json($method, self::BASE . $path, ['X-Auth-Token' => $this->token], $body, 25);
		$decoded = json_decode($res['body'], true);
		$json    = is_array($decoded) ? $decoded : null;

		// 2xx = success (201 on create). 401 bad token, 422/400 validation.
		$ok = $res['status'] >= 200 && $res['status'] < 300;

		$error = '';
		if (!$ok) {
			$error = self::extractError($json, $res['status'], $res['error'] ?? '');
		}

		return [
			'ok'     => $ok,
			'status' => $res['status'],
			'body'   => $res['body'],
			'json'   => $json,
			'error'  => $error,
		];
	}

	private static function extractError(?array $json, int $status, string $transport): string {
		if (is_array($json)) {
			if (!empty($json['errors']) && is_array($json['errors'])) {
				$parts = [];
				foreach ($json['errors'] as $field => $messages) {
					$msg     = is_array($messages) ? implode(', ', array_map('strval', $messages)) : (string)$messages;
					$parts[] = is_int($field) ? $msg : $field . ': ' . $msg;
				}
				if ($parts) {
					return implode('; ', $parts);
				}
			}
			if (!empty($json['message']) && is_scalar($json['message'])) {
				return (string)$json['message'];
			}
			if (!empty($json['error']) && is_scalar($json['error'])) {
				return (string)$json['error'];
			}
		}
		if ($status === 401) {
			return 'Invalid API token (HTTP 401)';
		}
		return 'HTTP ' . $status . ($transport ? ' ' . $transport : '');
	}
}
