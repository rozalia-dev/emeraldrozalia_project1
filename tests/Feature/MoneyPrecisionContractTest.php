<?php

namespace Tests\Feature;

use App\Support\Money;
use Tests\TestCase;

class MoneyPrecisionContractTest extends TestCase
{
    public function test_money_rounding_is_decimal_text_based_and_half_up(): void
    {
        $this->assertSame('0.01', Money::round('0.005'));
        $this->assertSame('0.02', Money::round('0.015'));
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('0.30', Money::round(0.1 + 0.2));
        $this->assertSame('-0.02', Money::round('-0.015'));
    }

    public function test_money_operations_use_minor_units_for_checkout_rules(): void
    {
        $this->assertSame('29.97', Money::multiply('9.99', 3));
        $this->assertSame('12.50', Money::percentage('100.00', '12.50'));
        $this->assertSame('0.01', Money::percentage('0.05', '25'));
        $this->assertSame(-1, Money::compare('9.99', '10.00'));
        $this->assertSame('8.00', Money::subtract('10.00', '2.00'));
    }
}
