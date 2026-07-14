<?php
namespace Opencart\Admin\Controller\Extension\CcKeepincrm\Module;

require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/crypto.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/settings.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/logger.php';
require_once DIR_EXTENSION . 'cc_keepincrm/system/library/cckc/client.php';

use Opencart\System\Library\Cckc\Crypto;
use Opencart\System\Library\Cckc\Settings;
use Opencart\System\Library\Cckc\Logger;
use Opencart\System\Library\Cckc\Client;

class CcKeepincrm extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/cc_keepincrm/module/cc_keepincrm';

	private function jsonResponse(array $data): void {
		if (ob_get_level() > 0) {
			ob_clean();
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}

	public function index(): void {
		$this->load->language($this->route);
		$this->document->setTitle($this->language->get('heading_title'));

		$settings = new Settings($this->config);
		$all = $settings->all();

		$data = [];
		foreach (Settings::defaults() as $key => $default) {
			$data[$key] = $all[$key];
		}
		// Never echo the secret back; expose a "set" flag for the placeholder.
		$data['api_key_set'] = ($all['api_key'] ?? '') !== '' ? 1 : 0;
		$data['api_key'] = '';

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'])],
		];
		$data['save']       = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token']);
		$data['test']       = $this->url->link($this->route . '.test', 'user_token=' . $this->session->data['user_token']);
		$data['log']        = $this->url->link($this->route . '.log', 'user_token=' . $this->session->data['user_token']);
		$data['back']       = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['user_token'] = $this->session->data['user_token'];

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->route, $data));
	}

	public function save(): void {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->jsonResponse(['error' => $this->language->get('error_permission')]);
			return;
		}
		$post = $this->request->post;

		$data = [];
		foreach (Settings::defaults() as $key => $default) {
			$field = 'module_cc_keepincrm_' . $key;
			if (in_array($key, Settings::SECRET_KEYS, true)) {
				$plain = trim((string)($post[$field] ?? ''));
				if ($plain !== '') {
					$data[$field] = Crypto::encrypt($plain);            // new secret
				} else {
					$data[$field] = (string)$this->config->get($field); // keep existing (encrypted)
				}
			} else {
				$data[$field] = isset($post[$field]) ? $post[$field] : $default;
			}
		}

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_cc_keepincrm', $data);

		$this->jsonResponse(['success' => $this->language->get('text_success')]);
	}

	public function test(): void {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('access', $this->route)) {
			$this->jsonResponse(['error' => $this->language->get('error_permission')]);
			return;
		}
		$settings = new Settings($this->config);

		// Use the token typed into the form when present, else the stored one.
		$key = trim((string)($this->request->post['api_key'] ?? '')) ?: (string)$settings->get('api_key', '');

		if ($key === '') {
			$this->jsonResponse(['ok' => false, 'error' => 'API token empty']);
			return;
		}
		$res = (new Client($key))->testConnection();
		$this->jsonResponse(['ok' => (bool)$res['ok'], 'error' => $res['ok'] ? '' : $res['error']]);
	}

	public function log(): void {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('access', $this->route)) {
			$this->response->setOutput($this->language->get('error_permission'));
			return;
		}
		$logger = new Logger($this->db);
		$data['rows'] = $logger->recent(100);
		$data['user_token'] = $this->session->data['user_token'];
		$this->response->setOutput($this->load->view('extension/cc_keepincrm/module/cc_keepincrm_log', $data));
	}

	public function install(): void {
		$prefix = DB_PREFIX;
		$this->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}cc_keepincrm_sync` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`order_id` BIGINT UNSIGNED NOT NULL,
			`external_id` VARCHAR(128) DEFAULT NULL,
			`status` VARCHAR(16) NOT NULL DEFAULT 'pending',
			`attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			`last_error` TEXT NULL,
			`request_excerpt` MEDIUMTEXT NULL,
			`response_excerpt` MEDIUMTEXT NULL,
			`created_at` DATETIME NOT NULL,
			`updated_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `order_id` (`order_id`),
			KEY `status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('cc_keepincrm_order_history_added');
		$this->model_setting_event->addEvent([
			'code'        => 'cc_keepincrm_order_history_added',
			'description' => 'CatCode KeepinCRM Sync — push order to KeepinCRM on placement / status change',
			// OC 4.x fires model events as <route>.<method> (dot before the method).
			'trigger'     => 'catalog/model/checkout/order.addHistory/after',
			'action'      => 'extension/cc_keepincrm/events.orderHistoryAdded',
			'status'      => 1,
			'sort_order'  => 20,
		]);

		$this->load->model('setting/cron');
		try { $this->model_setting_cron->deleteCronByCode('cc_keepincrm_retry'); } catch (\Throwable $e) {}
		$this->model_setting_cron->addCron('cc_keepincrm_retry', 'CatCode KeepinCRM Sync — retry failed pushes', 'hour', 'extension/cc_keepincrm/cron.retry', true);

		$this->load->model('user/user_group');
		try {
			$this->model_user_user_group->addPermission((int)$this->user->getGroupId(), 'access', $this->route);
			$this->model_user_user_group->addPermission((int)$this->user->getGroupId(), 'modify', $this->route);
		} catch (\Throwable $e) {}
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		try { $this->model_setting_event->deleteEventByCode('cc_keepincrm_order_history_added'); } catch (\Throwable $e) {}
		$this->load->model('setting/cron');
		try { $this->model_setting_cron->deleteCronByCode('cc_keepincrm_retry'); } catch (\Throwable $e) {}
		// Table preserved to keep sync history.
	}
}
