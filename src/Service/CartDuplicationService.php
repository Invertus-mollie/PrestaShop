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
 */

namespace Mollie\Service;

use Cart;
use CartRule;
use Context;
use Mollie\Config\Config;
use Mollie\Handler\CartRule\CartRuleQuantityResetHandlerInterface;

class CartDuplicationService
{
	/**
	 * @var CartRuleDuplicationService
	 */
	private $cartRuleDuplicationService;

	/**
	 * @var CartRuleQuantityResetHandlerInterface
	 */
	private $cartRuleQuantityResetHandlerInterface;

	public function __construct(
		CartRuleDuplicationService $cartRuleDuplicationService,
		CartRuleQuantityResetHandlerInterface $cartRuleQuantityResetHandlerInterface
	) {
		$this->cartRuleDuplicationService = $cartRuleDuplicationService;
		$this->cartRuleQuantityResetHandlerInterface = $cartRuleQuantityResetHandlerInterface;
	}

	/**
	 * @param int $cartId
	 * @param string $backtraceLocation
	 *
	 * @return int
	 *
	 * @throws \Exception
	 */
	public function restoreCart($cartId, $backtraceLocation)
	{
		$context = Context::getContext();
		$cart = new Cart($cartId);
		/* @phpstan-ignore-next-line */
		$cartRules = $cart->getCartRules(CartRule::FILTER_ACTION_ALL, false);

		if ($backtraceLocation === Config::RESTORE_CART_BACKTRACE_MEMORIZATION_SERVICE) {
			$this->cartRuleQuantityResetHandlerInterface->handle($cart, $cartRules);
		}
		$duplication = $cart->duplicate();
		if ($duplication['success']) {
			/** @var Cart $duplicatedCart */
			$duplicatedCart = $duplication['cart'];

			$context->cookie->__set('id_cart', $duplicatedCart->id);
			$context->cart = $duplicatedCart;
			$context->cookie->write();
			$this->cartRuleDuplicationService->restoreCartRules($cartRules);

			return $duplicatedCart->id;
		}

		return 0;
	}
}
