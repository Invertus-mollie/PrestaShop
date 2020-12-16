<?php
/**
 * Copyright (c) 2012-2020, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @author     Mollie B.V. <info@mollie.nl>
 * @copyright  Mollie B.V.
 * @license    Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 *
 * @category   Mollie
 *
 * @see       https://www.mollie.nl
 * @codingStandardsIgnoreStart
 */

namespace Mollie\Service;

use Configuration;
use Exception;
use Mollie\Adapter\ConfigurationAdapter;
use Mollie\Config\Config;
use Mollie\Repository\CountryRepository;
use Mollie\Repository\PaymentMethodRepository;
use Mollie\Service\PaymentMethod\PaymentMethodSortProviderInterface;
use Mollie\Utility\CartPriceUtility;
use Mollie\Utility\UrlPathUtility;
use MolliePrefix\Mollie\Api\Exceptions\ApiException;
use MolliePrefix\Mollie\Api\MollieApiClient;
use MolliePrefix\Mollie\Api\Resources\BaseCollection;
use MolliePrefix\Mollie\Api\Resources\MethodCollection;
use MolliePrefix\Mollie\Api\Resources\Order as MollieOrderAlias;
use MolliePrefix\Mollie\Api\Resources\Payment;
use MolliePrefix\Mollie\Api\Resources\PaymentCollection;
use MolPaymentMethod;
use PrestaShopDatabaseException;
use PrestaShopException;
use PrestaShopLogger;
use SmartyException;
use Tools;

class ApiService
{
	private $errors = [];

	/**
	 * @var PaymentMethodRepository
	 */
	private $methodRepository;

	/**
	 * @var CountryRepository
	 */
	private $countryRepository;

	/**
	 * @var PaymentMethodSortProviderInterface
	 */
	private $paymentMethodSortProvider;

	/**
	 * @var ConfigurationAdapter
	 */
	private $configurationAdapter;

	/**
	 * @var int
	 */
	private $environment;

	/*
	 * @var TransactionService
	 */
	private $transactionService;

	public function __construct(
		PaymentMethodRepository $methodRepository,
		CountryRepository $countryRepository,
		PaymentMethodSortProviderInterface $paymentMethodSortProvider,
		ConfigurationAdapter $configurationAdapter,
		TransactionService $transactionService
	) {
		$this->countryRepository = $countryRepository;
		$this->paymentMethodSortProvider = $paymentMethodSortProvider;
		$this->methodRepository = $methodRepository;
		$this->configurationAdapter = $configurationAdapter;
		$this->environment = (int) $this->configurationAdapter->get(Config::MOLLIE_ENVIRONMENT);
		$this->transactionService = $transactionService;
	}

	public function setApiKey($apiKey, $moduleVersion)
	{
		$api = new MollieApiClient();
		$context = Context::getContext();
		if ($apiKey) {
			try {
				$api->setApiKey($apiKey);
			} catch (ApiException $e) {
				return;
			}
		} elseif (!empty($context->employee)
			&& Tools::getValue('Mollie_Api_Key')
			&& $context->controller instanceof AdminModulesController
		) {
			$api->setApiKey(Tools::getValue('Mollie_Api_Key'));
		}
		if (defined('_TB_VERSION_')) {
			$api->addVersionString('ThirtyBees/'._TB_VERSION_);
			$api->addVersionString("MollieThirtyBees/{$moduleVersion}");
		} else {
			$api->addVersionString('PrestaShop/'._PS_VERSION_);
			$api->addVersionString("MolliePrestaShop/{$moduleVersion}");
		}

		return $api;
	}

    /**
     * @param MollieApiClient $api
     * @param string $paymentId
     * @param string $currencyIso
     *
     * @return array
     */
    public function getPaymentMethodOrderTotalRestriction(MollieApiClient $api, $paymentId, $currencyIso)
    {
        try {
            $paymentMethodConfig = $api->methods->get($paymentId, [
                'currency' => $currencyIso
            ])->getArrayCopy();

        } catch (Exception $e) {
            PrestaShopLogger::addLog('Mollie returned error on getPaymentMethodOrderTotalRestriction: ' . $e->getMessage());
            return null;
        }

        return $paymentMethodConfig;
    }

	/**
	 * Get payment methods to show on the configuration page.
	 *
	 * @param MollieApiClient $api
	 * @param string $path
	 *
	 * @return array
	 *
	 * @since 3.0.0
	 * @since 3.4.0 public
	 *
	 * @public ✓ This method is part of the public API
	 */
	public function getMethodsForConfig(MollieApiClient $api, $path)
	{
		$notAvailable = [];
		try {
			/** @var BaseCollection|MethodCollection $apiMethods */
			$apiMethods = $api->methods->allActive(['resource' => 'orders', 'include' => 'issuers', 'includeWallets' => 'applepay']);
			$apiMethods = $apiMethods->getArrayCopy();
		} catch (Exception $e) {
			$this->errors[] = $e->getMessage();

			return [];
		}

		if (!count($apiMethods)) {
			return [];
		}

		$methods = [];
		$deferredMethods = [];
		$isSSLEnabled = $this->configurationAdapter->get('PS_SSL_ENABLED_EVERYWHERE');
		foreach ($apiMethods as $apiMethod) {
			$tipEnableSSL = false;
			if (Config::APPLEPAY === $apiMethod->id && !$isSSLEnabled) {
				$notAvailable[] = $apiMethod->id;
				$tipEnableSSL = true;
			}
			$deferredMethods[] = [
				'id' => $apiMethod->id,
				'name' => $apiMethod->description,
				'available' => !in_array($apiMethod->id, $notAvailable),
				'image' => (array) $apiMethod->image,
				'issuers' => $apiMethod->issuers,
				'tipEnableSSL' => $tipEnableSSL,
			];
		}
		$availableApiMethods = array_column(array_map(function ($apiMethod) {
			return (array) $apiMethod;
		}, $apiMethods), 'id');
		if (in_array('creditcard', $availableApiMethods)) {
			foreach ([Config::CARTES_BANCAIRES => 'Cartes Bancaires'] as $id => $name) {
				$deferredMethods[] = [
					'id' => $id,
					'name' => $name,
					'available' => !in_array($id, $notAvailable),
					'image' => [
						'size1x' => UrlPathUtility::getMediaPath("{$path}views/img/{$id}_small.png"),
						'size2x' => UrlPathUtility::getMediaPath("{$path}views/img/{$id}.png"),
						'svg' => UrlPathUtility::getMediaPath("{$path}views/img/{$id}.svg"),
					],
					'issuers' => null,
				];
			}
		}
		ksort($methods);
		$methods = array_values($methods);
		foreach ($deferredMethods as $deferredMethod) {
			$methods[] = $deferredMethod;
		}

		$methods = $this->getMethodsObjForConfig($methods);
		$methods = $this->getMethodsCountriesForConfig($methods);
		$methods = $this->getExcludedCountriesForConfig($methods);
		$methods = $this->paymentMethodSortProvider->getSortedInAscendingWayForConfiguration($methods);

		return $methods;
	}

	private function getMethodsObjForConfig($apiMethods)
	{
		$methods = [];
		$defaultPaymentMethod = new MolPaymentMethod();
		$defaultPaymentMethod->enabled = false;
		$defaultPaymentMethod->title = '';
		$defaultPaymentMethod->method = 'payments';
		$defaultPaymentMethod->description = '';
		$defaultPaymentMethod->is_countries_applicable = false;
		$defaultPaymentMethod->minimal_order_value = '';
		$defaultPaymentMethod->max_order_value = '';
		$defaultPaymentMethod->surcharge = 0;
		$defaultPaymentMethod->surcharge_fixed_amount = '';
		$defaultPaymentMethod->surcharge_percentage = '';
		$defaultPaymentMethod->surcharge_limit = '';

		foreach ($apiMethods as $apiMethod) {
			$paymentId = $this->methodRepository->getPaymentMethodIdByMethodId($apiMethod['id'], $this->environment);
			if ($paymentId) {
				$paymentMethod = new MolPaymentMethod((int) $paymentId);
				$methods[$apiMethod['id']] = $apiMethod;
				$methods[$apiMethod['id']]['obj'] = $paymentMethod;
				continue;
			}
			$defaultPaymentMethod->id_method = $apiMethod['id'];
			$defaultPaymentMethod->method_name = $apiMethod['name'];
			$methods[$apiMethod['id']] = $apiMethod;
			$methods[$apiMethod['id']]['obj'] = $defaultPaymentMethod;
		}

		$availableApiMethods = array_column(array_map(function ($apiMethod) {
			return (array) $apiMethod;
		}, $apiMethods), 'id');
		if (in_array('creditcard', $availableApiMethods)) {
			foreach ([Config::CARTES_BANCAIRES => 'Cartes Bancaires'] as $value => $apiMethod) {
				$paymentId = $this->methodRepository->getPaymentMethodIdByMethodId($value, $this->environment);
				if ($paymentId) {
					$paymentMethod = new MolPaymentMethod((int) $paymentId);
					$methods[$value]['obj'] = $paymentMethod;
					continue;
				}
				$defaultPaymentMethod->id_method = $value;
				$defaultPaymentMethod->method_name = $apiMethod;
				$methods[$value]['obj'] = $defaultPaymentMethod;
			}
		}

		return $methods;
	}

	private function getMethodsCountriesForConfig(&$methods)
	{
		foreach ($methods as $key => $method) {
			$methods[$key]['countries'] = $this->countryRepository->getMethodCountryIds($key);
		}

		return $methods;
	}

	private function getExcludedCountriesForConfig(&$methods)
	{
		foreach ($methods as $key => $method) {
			$methods[$key]['excludedCountries'] = $this->countryRepository->getExcludedCountryIds($key);
		}

		return $methods;
	}

	/**
	 * @param MollieApiClient $api
	 * @param string $transactionId
	 * @param bool $process Process the new payment/order status
	 *
	 * @return array|null
	 *
	 * @throws ApiException
	 * @throws PrestaShopDatabaseException
	 * @throws PrestaShopException
	 *
	 * @since 3.3.0
	 * @since 3.3.2 $process option
	 */
	public function getFilteredApiPayment($api, $transactionId, $process = false)
	{
		$payment = $api->payments->get($transactionId);
		if ($process) {
			$this->transactionService->processTransaction($payment);
		}

		if (method_exists($payment, 'refunds')) {
			$refunds = $payment->refunds();
			if (empty($refunds)) {
				$refunds = [];
			}
			$refunds = array_map(function ($refund) {
				return array_intersect_key(
					(array) $refund,
					array_flip([
						'resource',
						'id',
						'amount',
						'createdAt',
					]));
			}, (array) $refunds);
			$payment = array_intersect_key(
				(array) $payment,
				array_flip([
					'resource',
					'id',
					'mode',
					'amount',
					'settlementAmount',
					'amountRefunded',
					'amountRemaining',
					'description',
					'method',
					'status',
					'createdAt',
					'paidAt',
					'canceledAt',
					'expiresAt',
					'failedAt',
					'metadata',
					'isCancelable',
				])
			);
			$payment['refunds'] = (array) $refunds;
		} else {
			$payment = null;
		}

		return $payment;
	}

	/**
	 * @param MollieApiClient $api
	 * @param string $transactionId
	 *
	 * @return array|MollieOrderAlias|null
	 *
	 * @throws ApiException
	 */
	public function getFilteredApiOrder($api, $transactionId)
	{
		/** @var MollieOrderAlias $order */
		$mollieOrder = $api->orders->get(
			$transactionId,
			[
				'embed' => 'payments',
				'include' => [
						'details' => 'remainderDetails',
					],
			]
		);

		if (method_exists($mollieOrder, 'refunds')) {
			$refunds = $mollieOrder->refunds();
			if (empty($refunds)) {
				$refunds = [];
			}
			$refunds = array_map(function ($refund) {
				return array_intersect_key(
					(array) $refund,
					array_flip([
						'resource',
						'id',
						'amount',
						'createdAt',
					]));
			}, (array) $refunds);
			$order = array_intersect_key(
				(array) $mollieOrder,
				array_flip([
					'resource',
					'id',
					'mode',
					'amount',
					'settlementAmount',
					'amountCaptured',
					'status',
					'method',
					'metadata',
					'isCancelable',
					'createdAt',
					'lines',
				])
			);
			$order['refunds'] = (array) $refunds;
		} else {
			$order = null;
		}

		/** @var PaymentCollection $molliePayments */
		$molliePayments = $mollieOrder->payments();

		/** @var Payment $payment */
		foreach ($molliePayments as $payment) {
			$amountRemaining = [
				'value' => '0.00',
				'currency' => $payment->amount->currency,
			];
			$order['availableRefundAmount'] = $payment->amountRemaining ?: $amountRemaining;
			$order['details'] = $payment->details !== null ? $payment->details : new \stdClass();
		}

		return $order;
	}

	/**
	 * Get the selected API.
	 *
	 * @throws PrestaShopException
	 *
	 * @since 3.3.0
	 *
	 * @public ✓ This method is part of the public API
	 */
	public static function selectedApi($selectedApi)
	{
		if (!in_array($selectedApi, [Config::MOLLIE_ORDERS_API, Config::MOLLIE_PAYMENTS_API])) {
			$selectedApi = Configuration::get(Config::MOLLIE_API);
			if (!$selectedApi
				|| !in_array($selectedApi, [Config::MOLLIE_ORDERS_API, Config::MOLLIE_PAYMENTS_API])
				|| CartPriceUtility::checkRoundingMode()
			) {
				$selectedApi = Config::MOLLIE_PAYMENTS_API;
			}
		}

		return $selectedApi;
	}
}
