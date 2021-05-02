<?php
namespace Opencart\Install\Controller\Upgrade;
class Upgrade7 extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('upgrade/upgrade');

		$json = [];

		try {
			// Customer Group
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "customer_group' AND COLUMN_NAME = 'name'");

			if ($query->num_rows) {
				$customer_group_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_group`");

				foreach ($customer_group_query->rows as $customer_group) {
					$language_query = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "language`");

					foreach ($language_query->rows as $language) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_group_description` SET `customer_group_id` = '" . (int)$customer_group['customer_group_id'] . "', `language_id` = '" . (int)$language['language_id'] . "', `name` = '" . $this->db->escape($customer_group['name']) . "'");
					}
				}

				$this->db->query("ALTER TABLE `" . DB_PREFIX . "customer_group` DROP `name`");
			}

			// Affiliate customer merge code
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "affiliate'");

			if ($query->num_rows) {
				// Removing affiliate and moving to the customer account.
				$config = new \Opencart\System\Engine\Config();

				$setting_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0'");

				foreach ($setting_query->rows as $setting) {
					$config->set($setting['key'], $setting['value']);
				}

				$affiliate_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "affiliate`");

				foreach ($affiliate_query->rows as $affiliate) {
					$customer_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer` WHERE `email` = '" . $this->db->escape($affiliate['email']) . "'");

					if (!$customer_query->num_rows) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "customer` SET `customer_group_id` = '" . (int)$config->get('config_customer_group_id') . "', `language_id` = '" . (int)$config->get('config_customer_group_id') . "', `firstname` = '" . $this->db->escape($affiliate['firstname']) . "', `lastname` = '" . $this->db->escape($affiliate['lastname']) . "', `email` = '" . $this->db->escape($affiliate['email']) . "', `telephone` = '" . $this->db->escape($affiliate['telephone']) . "', `password` = '" . $this->db->escape($affiliate['password']) . "', `cart` = '" . $this->db->escape(json_encode([])) . "', `wishlist` = '" . $this->db->escape(json_encode([])) . "', `newsletter` = '0', `custom_field` = '" . $this->db->escape(json_encode([])) . "', `ip` = '" . $this->db->escape($affiliate['ip']) . "', `status` = '" . $this->db->escape($affiliate['status']) . "', `date_added` = '" . $this->db->escape($affiliate['date_added']) . "'");

						$customer_id = $this->db->getLastId();

						$this->db->query("INSERT INTO `" . DB_PREFIX . "address` SET `customer_id` = '" . (int)$customer_id . "', `firstname` = '" . $this->db->escape($affiliate['firstname']) . "', `lastname` = '" . $this->db->escape($affiliate['lastname']) . "', `company` = '" . $this->db->escape($affiliate['company']) . "', `address_1` = '" . $this->db->escape($affiliate['address_1']) . "', `address_2` = '" . $this->db->escape($affiliate['address_2']) . "', `city` = '" . $this->db->escape($affiliate['city']) . "', `postcode` = '" . $this->db->escape($affiliate['postcode']) . "', `zone_id` = '" . (int)$affiliate['zone_id'] . "', `country_id` = '" . (int)$affiliate['country_id'] . "', `custom_field` = '" . $this->db->escape(json_encode([])) . "'");

						$address_id = $this->db->getLastId();

						$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `address_id` = '" . (int)$address_id . "' WHERE `customer_id` = '" . (int)$customer_id . "'");
					} else {
						$customer_id = $customer_query->row['customer_id'];
					}

					$customer_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_affiliate` WHERE `customer_id` = '" . (int)$customer_id . "'");

					if (!$customer_query->num_rows) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_affiliate` SET `customer_id` = '" . (int)$customer_id . "', `company` = '" . $this->db->escape($affiliate['company']) . "', `tracking` = '" . $this->db->escape($affiliate['code']) . "', `commission` = '" . (float)$affiliate['commission'] . "', `tax` = '" . $this->db->escape($affiliate['tax']) . "', `payment` = '" . $this->db->escape($affiliate['payment']) . "', `cheque` = '" . $this->db->escape($affiliate['cheque']) . "', `paypal` = '" . $this->db->escape($affiliate['paypal']) . "', `bank_name` = '" . $this->db->escape($affiliate['bank_name']) . "', `bank_branch_number` = '" . $this->db->escape($affiliate['bank_branch_number']) . "', `bank_account_name` = '" . $this->db->escape($affiliate['bank_account_name']) . "', `bank_account_number` = '" . $this->db->escape($affiliate['bank_account_number']) . "', `status` = '" . (int)(isset($affiliate['approved']) ? $affiliate['approved'] : $affiliate['status']) . "', `date_added` = '" . $this->db->escape($affiliate['date_added']) . "'");
					}

					$affiliate_transaction_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "affiliate_transaction` WHERE `affiliate_id` = '" . (int)$affiliate['affiliate_id'] . "'");

					foreach ($affiliate_transaction_query->rows as $affiliate_transaction) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_transaction` SET `customer_id` = '" . (int)$customer_id . "', `order_id` = '" . (int)$affiliate_transaction['order_id'] . "', `description` = '" . $this->db->escape($affiliate_transaction['description']) . "', `amount` = '" . (float)$affiliate_transaction['amount'] . "', `date_added` = '" . $this->db->escape($affiliate_transaction['date_added']) . "'");

						$this->db->query("DELETE FROM `" . DB_PREFIX . "affiliate_transaction` WHERE `affiliate_transaction_id` = '" . (int)$affiliate_transaction['affiliate_transaction_id'] . "'");
					}

					$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `affiliate_id` = '" . (int)$customer_id . "' WHERE `affiliate_id` = '" . (int)$affiliate['affiliate_id'] . "'");
				}

				$this->db->query("DROP TABLE `" . DB_PREFIX . "affiliate`");

				$affiliate_query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "affiliate_activity'");

				if ($affiliate_query->num_rows) {
					$this->db->query("DROP TABLE `" . DB_PREFIX . "affiliate_activity`");
				}

				$affiliate_query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "affiliate_login'");

				if ($affiliate_query->num_rows) {
					$this->db->query("DROP TABLE `" . DB_PREFIX . "affiliate_login`");
				}

				$this->db->query("DROP TABLE `" . DB_PREFIX . "affiliate_transaction`");
			}

			// order_custom_field
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "order_field'");

			if ($query->num_rows) {
				$order_field_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_field`");

				foreach ($order_field_query->rows as $result) {
					$order_custom_field_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_custom_field` WHERE `order_id` = '" . (int)$result['order_id'] . "' AND `custom_field_id` = '" . (int)$result['custom_field_id'] . "' AND `custom_field_value_id` = '" . (int)$result['custom_field_value_id'] . "' AND `name` = '" . $this->db->escape($result['name']) . "' AND `value` = '" . $this->db->escape($result['value']) . "'");

					if (!$order_custom_field_query->num_rows) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "order_custom_field` SET `order_id` = '" . (int)$result['order_id'] . "', `custom_field_id` = '" . (int)$result['custom_field_id'] . "', `custom_field_value_id` = '" . (int)$result['custom_field_value_id'] . "', `name` = '" . $this->db->escape($result['name']) . "', `value` = '" . $this->db->escape($result['value']) . "'");
					}
				}

				$this->db->query("DROP TABLE `" . DB_PREFIX . "order_field`");
			}

			// order_recurring
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "order_recurring' AND COLUMN_NAME = 'created'");

			if ($query->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `date_added` = `created` WHERE `date_added` IS NULL or `date_added` = ''");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "order_recurring` DROP `created`");
			}

			// order_recurring
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "order_recurring' AND COLUMN_NAME = 'profile_id'");

			if ($query->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `recurring_id` = `profile_id` WHERE `recurring_id` IS NULL OR `recurring_id` = ''");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "order_recurring` DROP `profile_id`");
			}

			// order_recurring
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "order_recurring' AND COLUMN_NAME = 'profile_name'");

			if ($query->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `recurring_name` = `profile_name` WHERE `recurring_name` IS NULL or `recurring_name` = ''");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "order_recurring` DROP `profile_name`");
			}

			// order_recurring
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "order_recurring' AND COLUMN_NAME = 'profile_description'");

			if ($query->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `recurring_description` = `profile_description` WHERE `recurring_description` IS NULL OR `recurring_description` = ''");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "order_recurring` DROP `profile_description`");
			}

			// order_recurring_transaction
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "order_recurring_transaction' AND COLUMN_NAME = 'created'");

			if ($query->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_recurring_transaction` SET `date_added` = `created` WHERE `date_added` IS NULL or `date_added` = ''");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "order_recurring_transaction` DROP `created`");
			}

			// Api
			$query = $this->db->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "api' AND COLUMN_NAME = 'name'");

			if ($query->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "api` SET `name` = `username` WHERE `username` IS NULL or `username` = ''");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "api` DROP COLUMN `name`");
			}
		} catch(\ErrorException $exception) {
			$json['error'] = sprintf($this->language->get('error_exception'), $exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine());
		}

		if (!$json) {
			$json['success'] = sprintf($this->language->get('text_progress'), 7, 7, 8);

			$json['next'] = $this->url->link('upgrade/upgrade_8', [], true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
