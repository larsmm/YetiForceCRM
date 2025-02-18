<?php
/**
 * Main file to integration with WooCommerce.
 *
 * The file is part of the paid functionality. Using the file is allowed only after purchasing a subscription.
 * File modification allowed only with the consent of the system producer.
 *
 * @package Integration
 *
 * @copyright YetiForce S.A.
 * @license   YetiForce Public License 5.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

namespace App\Integrations;

use App\Exceptions\AppException;

/**
 * Main class to integration with WooCommerce.
 */
class WooCommerce
{
	/** @var string Servers table name */
	public const TABLE_NAME = 'i_#__woocommerce_servers';
	/** @var string Basic table name */
	public const LOG_TABLE_NAME = 'l_#__woocommerce';
	/** @var string Config table name */
	public const CONFIG_TABLE_NAME = 'i_#__woocommerce_config';
	/** @var string Map class table name */
	public const MAP_TABLE_NAME = 'i_#__woocommerce_map_class';

	/** @var \App\CronHandler|null Cron instance */
	public $cron;
	/** @var \App\Integrations\WooCommerce\Config Config. */
	public $config;
	/** @var \App\Integrations\WooCommerce\Connector\Base Connector with WooCommerce. */
	public $connector;
	/** @var \App\Integrations\WooCommerce\Synchronizer\Base[] Synchronizers instance */
	public $synchronizer = [];

	/**
	 * Constructor. Connect with WooCommerce and authorize.
	 *
	 * @param int              $serverId
	 * @param \App\CronHandler $cron
	 */
	public function __construct(int $serverId, ?\App\CronHandler $cron = null)
	{
		$this->cron = $cron;
		$this->config = WooCommerce\Config::getInstance($serverId);
	}

	/**
	 * Get connector.
	 *
	 * @return \App\Integrations\WooCommerce\Connector\Base
	 */
	public function getConnector(): WooCommerce\Connector\Base
	{
		if (null === $this->connector) {
			$className = '\\App\\Integrations\\WooCommerce\\Connector\\' . $this->config->get('connector') ?? 'HttpAuth';
			if (!class_exists($className)) {
				throw new AppException('ERR_CLASS_NOT_FOUND');
			}
			$this->connector = new $className($this->config);
			if (!$this->connector instanceof WooCommerce\Connector\Base) {
				throw new AppException('ERR_CLASS_MUST_BE||\App\Integrations\WooCommerce\Connector\Base');
			}
		}
		return $this->connector;
	}

	/**
	 * Get synchronizer object instance.
	 *
	 * @param string $name
	 *
	 * @return \App\Integrations\WooCommerce\Synchronizer\Base
	 */
	public function getSync(string $name): WooCommerce\Synchronizer\Base
	{
		if (isset($this->synchronizer[$name])) {
			return $this->synchronizer[$name];
		}
		$className = "App\\Integrations\\WooCommerce\\Synchronizer\\{$name}";
		return $this->synchronizer[$name] = new $className($this);
	}

	/**
	 * Get information about Comarch ERP XL.
	 *
	 * @return array
	 */
	public function getInfo(): array
	{
		$connector = $this->getConnector();
		$response = $connector->request('GET', 'system_status');
		$response = \App\Json::decode($response);
		$info = '';
		$info .= "[environment][home_url]: {$response['environment']['home_url']}\n";
		$info .= "[environment][site_url]: {$response['environment']['site_url']}\n";
		$info .= "[environment][version]: {$response['environment']['version']}\n";
		$info .= "[environment][wp_version]: {$response['environment']['wp_version']}\n";
		$info .= "[environment][language]: {$response['environment']['language']}\n";
		$info .= "[environment][server_info]: {$response['environment']['server_info']}\n";
		$info .= "[environment][php_version]: {$response['environment']['php_version']}\n";
		$info .= "[environment][mysql_version_string]: {$response['environment']['mysql_version_string']}\n";
		$info .= "[environment][default_timezone]: {$response['environment']['default_timezone']}\n";
		$info .= "[database][wc_database_version]: {$response['database']['wc_database_version']}\n";
		$info .= "[settings][currency]: {$response['settings']['currency']}\n";
		$info .= "[settings][currency_symbol]: {$response['settings']['currency_symbol']}\n\n";

		$count = [];
		foreach ($response['post_type_counts'] as $value) {
			$count[$value['type']] = $value['count'];
		}
		return [
			'info' => trim($info),
			'count' => $count
		];
	}
}
