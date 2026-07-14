<?php
namespace Opencart\Catalog\Controller\Extension\CcKeepincrm;

require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/settings.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/logger.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/order_mapper.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/sender.php';

use Opencart\System\Library\Cckc\Settings;
use Opencart\System\Library\Cckc\Logger;
use Opencart\System\Library\Cckc\OrderMapper;
use Opencart\System\Library\Cckc\Sender;

/**
 * Retry of failed KeepinCRM pushes. Registered as extension/cc_keepincrm/cron.retry.
 */
class Cron extends \Opencart\System\Engine\Controller {

	public function retry(): void {
		$settings = new Settings($this->config);
		if ($settings->get('status', '0') !== '1' || $settings->get('retry_enabled', '1') !== '1' || !$settings->isConfigured()) {
			return;
		}

		$logger = new Logger($this->db);
		$sender = new Sender($this->db, $settings);
		$maxAtt = (int)$settings->get('max_attempts', 5);

		$this->load->model('checkout/order');

		foreach ($logger->retryable($maxAtt) as $row) {
			$orderId = (int)$row['order_id'];
			$order   = $this->model_checkout_order->getOrder($orderId);
			if (!$order) {
				continue;
			}
			$payload = OrderMapper::build($this->db, $order, $settings);
			if (empty($payload['products'])) {
				continue;
			}
			$sender->send($orderId, $payload);
		}
	}
}
