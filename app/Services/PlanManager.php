<?php

namespace App\Services;

use App\Constants\PaymentProviderPlanPriceType;
use App\Constants\PlanType;
use App\Models\Currency;
use App\Models\PaymentProvider;
use App\Models\Plan;
use App\Models\PlanMeter;
use App\Models\PlanMeterPaymentProviderData;
use App\Models\PlanPaymentProviderData;
use App\Models\PlanPrice;
use App\Models\PlanPricePaymentProviderData;
use App\Models\Product;
use Illuminate\Support\Collection;

class PlanManager
{
    public function getPaymentProviderProductId(Plan $plan, PaymentProvider $paymentProvider): ?string
    {
        $result = PlanPaymentProviderData::where('plan_id', $plan->id)
            ->where('payment_provider_id', $paymentProvider->id)
            ->first();

        if ($result) {
            return $result->payment_provider_product_id;
        }

        return null;
    }

    public function findByPaymentProviderProductId(PaymentProvider $paymentProvider, string $paymentProviderProductId): ?Plan
    {
        $result = PlanPaymentProviderData::where('payment_provider_id', $paymentProvider->id)
            ->where('payment_provider_product_id', $paymentProviderProductId)
            ->first();

        if ($result) {
            return Plan::find($result->plan_id);
        }

        return null;
    }

    public function addPaymentProviderProductId(Plan $plan, PaymentProvider $paymentProvider, string $paymentProviderProductId): void
    {
        PlanPaymentProviderData::create([
            'plan_id' => $plan->id,
            'payment_provider_id' => $paymentProvider->id,
            'payment_provider_product_id' => $paymentProviderProductId,
        ]);
    }

    public function getPaymentProviderPrices(PlanPrice $planPrice, PaymentProvider $paymentProvider): Collection
    {
        return PlanPricePaymentProviderData::where('plan_price_id', $planPrice->id)
            ->where('payment_provider_id', $paymentProvider->id)
            ->get();
    }

    public function getPaymentProviderPriceId(PlanPrice $planPrice, PaymentProvider $paymentProvider): ?string
    {
        $result = PlanPricePaymentProviderData::where('plan_price_id', $planPrice->id)
            ->where('payment_provider_id', $paymentProvider->id)
            ->first();

        if ($result) {
            return $result->payment_provider_price_id;
        }

        return null;
    }

    public function getPaymentProviderMeterId(PlanMeter $planMeter, PaymentProvider $paymentProvider): ?string
    {
        $result = $this->getPaymentProviderMeter($planMeter, $paymentProvider);

        if ($result) {
            return $result->payment_provider_plan_meter_id;
        }

        return null;
    }

    public function getPaymentProviderMeter(PlanMeter $planMeter, PaymentProvider $paymentProvider): ?PlanMeterPaymentProviderData
    {
        return PlanMeterPaymentProviderData::where('plan_meter_id', $planMeter->id)
            ->where('payment_provider_id', $paymentProvider->id)
            ->first();
    }

    public function addPaymentProviderMeterId(
        PlanMeter $planMeter,
        PaymentProvider $paymentProvider,
        string $paymentProviderMeterId,
        array $data = [],
    ): void {
        PlanMeterPaymentProviderData::create([
            'plan_meter_id' => $planMeter->id,
            'payment_provider_id' => $paymentProvider->id,
            'payment_provider_plan_meter_id' => $paymentProviderMeterId,
            'data' => $data,
        ]);
    }

    public function addPaymentProviderPriceId(
        PlanPrice $planPrice,
        PaymentProvider $paymentProvider,
        string $paymentProviderPriceId,
        PaymentProviderPlanPriceType $paymentProviderPlanPriceType = PaymentProviderPlanPriceType::MAIN_PRICE
    ): void {
        PlanPricePaymentProviderData::create([
            'plan_price_id' => $planPrice->id,
            'payment_provider_id' => $paymentProvider->id,
            'payment_provider_price_id' => $paymentProviderPriceId,
            'type' => $paymentProviderPlanPriceType->value,
        ]);
    }

    public function getActivePlanBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->where('is_active', true)->first();
    }

    public function getActivePlanById(int $id): ?Plan
    {
        return Plan::where('id', $id)->where('is_active', true)->first();
    }

    public function getAllActivePlans()
    {
        return Plan::where('is_active', true)->get();
    }

    public function getDefaultProduct(): ?Product
    {
        return Product::where('is_default', true)->first();
    }

    public function getAllPlansWithPrices(array $productSlugs = [], ?string $planType = null)
    {
        $defaultCurrency = config('app.default_currency');

        $defaultCurrencyObject = Currency::where('code', $defaultCurrency)->first();

        if (! $defaultCurrencyObject) {
            return new Collection;
        }

        if (count($productSlugs) > 0) {
            // only the plans that have default currency prices
            $result = Plan::where('is_active', true)
                ->with(['product' => function ($query) use ($productSlugs) {
                    $query->whereIn('slug', $productSlugs);
                }])
                ->whereHas('product', function ($query) use ($productSlugs) {
                    $query->whereIn('slug', $productSlugs);
                })
                ->whereHas('prices', function ($query) use ($defaultCurrencyObject) {
                    $query->where('currency_id', $defaultCurrencyObject->id);
                })
                ->with(['prices' => function ($query) use ($defaultCurrencyObject) {
                    $query->where('currency_id', $defaultCurrencyObject->id);
                }]);

            if ($planType) {
                $result->where('type', $planType);
            }

            return $result->get();
        }

        // only the plans that have default currency prices
        $result = Plan::where('is_active', true)
            ->whereHas('prices', function ($query) use ($defaultCurrencyObject) {
                $query->where('currency_id', $defaultCurrencyObject->id);
            })
            ->with(['prices' => function ($query) use ($defaultCurrencyObject) {
                $query->where('currency_id', $defaultCurrencyObject->id);
            }]);

        if ($planType) {
            $result->where('type', $planType);
        }

        return $result->get();
    }

    public function isPlanChangeable(Plan $plan)
    {
        if ($plan->type === PlanType::USAGE_BASED->value) {
            // usage based plans are not upgradable because users pay at the end of the billing cycle, and they can abuse the system
            // by using a lot of resources and then downgrading to a lower plan, and do that infinitely without paying
            return false;
        }

        return true;
    }
}
