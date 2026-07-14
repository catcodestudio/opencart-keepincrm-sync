<?php
namespace Opencart\Catalog\Controller\Extension\CcKeepincrm;

require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/settings.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/logger.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/order_mapper.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/sender.php';

use Opencart\System\Library\Cckc\Settings;
use Opencart\System\Library\Cckc\OrderMapper;
use Opencart\System\Library\Cckc\Sender;

class Events extends \Opencart\System\Engine\Controller {

	/**
	 * catalog/model/checkout/order.addHistory/after
	 * args: [order_id, order_status_id, comment, notify]
	 */
	public function orderHistoryAdded(string &$route, array &$args, mixed &$output): void {
		$settings = new Settings($this->config);
		if ($settings->get('status', '0') !== '1' || !$settings->isConfigured()) {
			return;
		}

		$orderId  = (int)($args[0] ?? 0);
		$statusId = (int)($args[1] ?? 0);
		if ($orderId <= 0 || $statusId <= 0) {
			return;
		}

		if ((string)$settings->get('send_on', 'create') === 'status') {
			if ($statusId !== (int)$settings->get('trigger_status', 0)) {
				return;
			}
		}
		// send_on === 'create': fire on the first real status; the journal's
		// UNIQUE(order_id) key guarantees a single push per order.

		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($orderId);
		if (!$order) {
			return;
		}

		$payload = OrderMapper::build($this->db, $order, $settings);
		if (empty($payload['jobs_attributes'])) {
			return;
		}

		$res = (new Sender($this->db, $settings))->send($orderId, $payload);
		$this->annotate($orderId, $statusId, $res);
	}

	/** Add an internal order note summarizing the sync result. */
	private function annotate(int $orderId, int $statusId, array $res): void {
		if (!empty($res['skipped'])) {
			return;
		}
		$note = !empty($res['ok'])
			? 'KeepinCRM: OK' . (!empty($res['external_id']) ? ' #' . $res['external_id'] : '')
			: 'KeepinCRM: ' . ($res['error'] ?? 'error');

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET
			order_id = " . (int)$orderId . ",
			order_status_id = " . (int)$statusId . ",
			notify = 0,
			comment = '" . $this->db->escape($note) . "',
			date_added = NOW()");
	}
}
