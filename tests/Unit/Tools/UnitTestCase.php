<?php

namespace Mollie\Tests\Unit\Tools;

use Mollie\Adapter\ConfigurationAdapter;
use Mollie\Adapter\LegacyContext;
use Mollie\Provider\OrderTotalProvider;
use Mollie\Provider\OrderTotalRestrictionProvider;
use Mollie\Provider\PaymentMethodCountryProvider;
use Mollie\Provider\PaymentMethodCurrencyProvider;
use Mollie\Service\OrderTotalService;
use MolPaymentMethod;
use PHPUnit\Framework\TestCase;

class UnitTestCase extends TestCase
{
	public function mockContext($countryCode, $currencyCode)
	{
		$contextMock = $this->getMockBuilder(LegacyContext::class)
			->disableOriginalConstructor()
			->getMock();

		$contextMock
			->method('getCountryIsoCode')
			->willReturn($countryCode)
		;

		$contextMock
			->method('getCurrencyIsoCode')
			->willReturn($currencyCode)
		;

		$contextMock
			->method('getCurrencyId')
			->willReturn(1)
		;

		$contextMock
			->method('getCountryId')
			->willReturn(1)
		;

		return $contextMock;
	}

	public function mockContextWithCookie($cookieValue)
	{
		$contextMock = $this->getMockBuilder(LegacyContext::class)
			->disableOriginalConstructor()
			->getMock();

		$contextMock
			->method('getCookieValue')
			->willReturn($cookieValue)
		;

		return $contextMock;
	}

	public function mockPaymentMethod($paymentName, $enabled)
	{
		$paymentMethod = $this->getMockBuilder(MolPaymentMethod::class)
			->disableOriginalConstructor()
			->getMock();

		$paymentMethod
			->method('getPaymentMethodName')
			->willReturn($paymentName)
		;

		$paymentMethod
			->method('getEnabled')
			->willReturn($enabled)
		;

		return $paymentMethod;
	}

	public function mockPaymentMethodCountryProvider($availableCountries)
	{
		$paymentMethodCountryProvider = $this->getMockBuilder(PaymentMethodCountryProvider::class)
			->disableOriginalConstructor()
			->getMock();

		$paymentMethodCountryProvider
			->method('provideAvailableCountriesByPaymentMethod')
			->willReturn($availableCountries)
		;

		return $paymentMethodCountryProvider;
	}

	public function mockPaymentMethodCurrencyProvider($availableCurrencies)
	{
		$paymentMethodCountryProvider = $this->getMockBuilder(PaymentMethodCurrencyProvider::class)
			->disableOriginalConstructor()
			->getMock();

		$paymentMethodCountryProvider
			->method('provideAvailableCurrenciesByPaymentMethod')
			->willReturn($availableCurrencies)
		;

		return $paymentMethodCountryProvider;
	}

	public function mockOrderTotalService($isOrderTotalHigherThanMaximum, $isOrderTotalLowerThanMinimum)
	{
		$orderTotalService = $this->getMockBuilder(OrderTotalService::class)
			->disableOriginalConstructor()
			->getMock();

		$orderTotalService
			->method('isOrderTotalLowerThanMinimumAllowed')
			->willReturn($isOrderTotalLowerThanMinimum)
		;

		$orderTotalService
			->method('isOrderTotalHigherThanMaximumAllowed')
			->willReturn($isOrderTotalHigherThanMaximum)
		;

		return $orderTotalService;
	}

	public function mockOrderTotalRestrictionProvider($minimumValue, $maximumValue)
	{
		$orderTotalRestrictionProvider = $this->getMockBuilder(OrderTotalRestrictionProvider::class)
			->disableOriginalConstructor()
			->getMock();

		$orderTotalRestrictionProvider
			->method('provideOrderTotalMinimumRestriction')
			->willReturn($minimumValue)
		;

		$orderTotalRestrictionProvider
			->method('provideOrderTotalMaximumRestriction')
			->willReturn($maximumValue)
		;

		return $orderTotalRestrictionProvider;
	}

	public function mockOrderTotalProvider($orderTotal)
	{
		$orderTotalProvider = $this->getMockBuilder(OrderTotalProvider::class)
			->disableOriginalConstructor()
			->getMock();

		$orderTotalProvider
			->method('provideOrderTotal')
			->willReturn($orderTotal)
		;

		return $orderTotalProvider;
	}

	public function mockConfigurationAdapter($configuration)
	{
		$configurationAdapter = $this->getMockBuilder(ConfigurationAdapter::class)
			->disableOriginalConstructor()
			->getMock();

		$configurationAdapter
			->method('get')
			->with('PS_SSL_ENABLED_EVERYWHERE')
			->willReturn($configuration['PS_SSL_ENABLED_EVERYWHERE'])
		;

		return $configurationAdapter;
	}
}
