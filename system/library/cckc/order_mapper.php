<?php
namespace Opencart\System\Library\Cckc;

require_once __DIR__ . '/settings.php';

/**
 * Turns an OpenCart order (model_checkout_order->getOrder plus order_product /
 * order_total rows) into a KeepinCRM "agreement" payload.
 *
 * KeepinCRM quirks (learned the hard way, generic 400/500 give no detail):
 *  - client_attributes.company must be non-empty (it is the client display name);
 *  - a client comment is rejected — the address rides in the agreement comment;
 *  - each job needs a nested product_attributes{title,price,currency,sku?}.
 */
class OrderMapper {
	public static function build($db, array $order, Settings $settings): array {
		$orderId  = (int)$order['order_id'];
		$skipZero = $settings->get('skip_zero', '1') === '1';
		$inclShip = $settings->get('include_ship', '1') === '1';
		$currency = (string)($order['currency_code'] ?? 'UAH');

		$rows = $db->query("SELECT op.product_id, op.name, op.model, op.quantity, op.price, op.total,
				COALESCE(p.sku, '') AS sku
			FROM `" . DB_PREFIX . "order_product` op
			LEFT JOIN `" . DB_PREFIX . "product` p ON p.product_id = op.product_id
			WHERE op.order_id = " . $orderId)->rows;

		$jobs = [];
		foreach ($rows as $r) {
			$qty   = (float)$r['quantity'];
			$price = round((float)$r['price'], 2);
			if ($qty <= 0) {
				continue;
			}
			if ($skipZero && $price * $qty <= 0.0001) {
				continue;
			}
			$name = (string)$r['name'];
			$sku  = (string)($r['sku'] !== '' ? $r['sku'] : $r['model']);
			$product = [
				'title'    => $name,
				'price'    => $price,
				'currency' => $currency,
			];
			if ($sku !== '') {
				$product['sku'] = $sku;
			}
			$jobs[] = [
				'amount'             => $qty,
				'title'              => $name,
				'product_attributes' => $product,
			];
		}

		if ($inclShip) {
			$ship     = $db->query("SELECT value FROM `" . DB_PREFIX . "order_total` WHERE order_id = " . $orderId . " AND code = 'shipping' LIMIT 1")->row;
			$shipCost = round((float)($ship['value'] ?? 0), 2);
			if ($shipCost > 0) {
				$shipName = '';
				if (isset($order['shipping_method']) && is_array($order['shipping_method'])) {
					$shipName = (string)($order['shipping_method']['name'] ?? '');
				} else {
					$shipName = (string)($order['shipping_method'] ?? '');
				}
				if ($shipName === '') {
					$shipName = 'Доставка';
				}
				$jobs[] = [
					'amount'             => 1,
					'title'              => $shipName,
					'product_attributes' => ['title' => $shipName, 'price' => $shipCost, 'currency' => $currency],
				];
			}
		}

		$paymentName = '';
		if (isset($order['payment_method']) && is_array($order['payment_method'])) {
			$paymentName = (string)($order['payment_method']['name'] ?? '');
		} else {
			$paymentName = (string)($order['payment_method'] ?? '');
		}

		$shipCity    = (string)($order['shipping_city'] ?? ($order['payment_city'] ?? ''));
		$shipAddress = trim(implode(', ', array_filter([
			(string)($order['shipping_address_1'] ?? ''),
			(string)($order['shipping_address_2'] ?? ''),
		])));
		if ($shipAddress === '') {
			$shipAddress = trim(implode(', ', array_filter([
				(string)($order['payment_address_1'] ?? ''),
				(string)($order['payment_address_2'] ?? ''),
			])));
		}
		$address = trim(implode(', ', array_filter([$shipCity, $shipAddress])));

		$person = trim((string)($order['firstname'] ?? '') . ' ' . (string)($order['lastname'] ?? ''));
		if ($person === '') {
			$person = (string)($order['email'] ?? '');
		}
		if ($person === '') {
			$person = 'Клієнт';
		}

		$client = [
			'company' => $person, // non-empty display name required by KeepinCRM
			'person'  => $person,
			'lead'    => true,
			'email'   => (string)($order['email'] ?? ''),
		];
		$phone = self::phone((string)($order['telephone'] ?? ''));
		if ($phone !== '') {
			$client['phones'] = [$phone];
		}

		$comment = (string)($order['comment'] ?? '');
		if ($paymentName !== '') {
			$comment = $comment !== '' ? $comment . ' | ' . $paymentName : $paymentName;
		}
		if ($address !== '') {
			$comment = $comment !== '' ? $comment . ' | ' . $address : $address;
		}

		$payload = [
			'title'                   => 'Замовлення #' . $orderId . ' (' . (string)$settings->get('source_label', 'OpenCart') . ')',
			'total'                   => round((float)($order['total'] ?? 0), 2),
			'currency'                => $currency,
			'products_total_as_total' => true,
			'client_attributes'       => $client,
			'jobs_attributes'         => $jobs,
		];
		if ($comment !== '') {
			$payload['comment'] = $comment;
		}

		foreach (['funnel_id', 'stage_id', 'source_id', 'main_responsible_id'] as $key) {
			$val = (int)$settings->get($key, 0);
			if ($val > 0) {
				$payload[$key] = $val;
			}
		}

		return $payload;
	}

	private static function phone(string $raw): string {
		$digits = preg_replace('/\D+/', '', $raw);
		if ($digits === '') {
			return '';
		}
		if (strlen($digits) === 10 && $digits[0] === '0') {
			$digits = '38' . $digits;
		}
		return '+' . $digits;
	}
}
