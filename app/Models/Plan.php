<?php

namespace App\Models;

use App\Constants\PaymentProviderConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'product_id',
        'interval_id',
        'interval_count',
        'has_trial',
        'trial_interval_id',
        'trial_interval_count',
        'is_active',
        'type',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function interval(): BelongsTo
    {
        return $this->belongsTo(Interval::class, 'interval_id');
    }

    public function trialInterval(): BelongsTo
    {
        return $this->belongsTo(Interval::class, 'trial_interval_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function paymentProviderData(): HasMany
    {
        return $this->hasMany(PlanPaymentProviderData::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(PlanMeter::class);
    }

    protected static function booted(): void
    {
        static::updating(function (Plan $plan) {
            // booleans are a bit tricky to compare, so we use boolval to compare them
            if ($plan->isDirty([
                'product_id',
                'interval_id',
                'interval_count',
                'trial_interval_id',
                'trial_interval_count',
            ]) || boolval($plan->getOriginal('has_trial')) !== boolval($plan->has_trial)) {
                // delete all except lemon squeezy stuff (because lemon squeezy data are not auto-created on plan update as with other providers)
                $plan->paymentProviderData()->where('payment_provider_id', '!=', PaymentProvider::where('slug', PaymentProviderConstants::LEMON_SQUEEZY_SLUG)?->first()?->id)->delete();
                foreach ($plan->prices as $planPrice) {
                    $planPrice->planPricePaymentProviderData()->delete();
                }
            }
        });

        static::deleting(function (Plan $plan) {
            $plan->paymentProviderData()->delete();
            foreach ($plan->prices as $planPrice) {
                $planPrice->planPricePaymentProviderData()->delete();
            }
        });
    }
}
