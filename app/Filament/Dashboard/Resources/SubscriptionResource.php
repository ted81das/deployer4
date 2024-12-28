<?php

namespace App\Filament\Dashboard\Resources;

use App\Constants\DiscountConstants;
use App\Constants\PlanPriceTierConstants;
use App\Constants\PlanPriceType;
use App\Constants\SubscriptionStatus;
use App\Filament\Dashboard\Resources\SubscriptionResource\ActionHandlers\DiscardSubscriptionCancellationActionHandler;
use App\Filament\Dashboard\Resources\SubscriptionResource\Pages;
use App\Filament\Dashboard\Resources\SubscriptionResource\RelationManagers\UsagesRelationManager;
use App\Mapper\SubscriptionStatusMapper;
use App\Models\Subscription;
use App\Services\ConfigManager;
use App\Services\PlanManager;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')->label(__('Plan')),
                Tables\Columns\TextColumn::make('price')->formatStateUsing(function (string $state, $record) {
                    $interval = $record->interval->name;
                    if ($record->interval_count > 1) {
                        $interval = $record->interval_count.' '.__(str()->of($record->interval->name)->plural()->toString());
                    }

                    return money($state, $record->currency->code).' / '.$interval;
                }),
                Tables\Columns\TextColumn::make('ends_at')->dateTime(config('app.datetime_format'))->label(__('Next Renewal')),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (string $state, SubscriptionStatusMapper $mapper): string => $mapper->mapForDisplay($state)),
                Tables\Columns\IconColumn::make('is_canceled_at_end_of_cycle')
                    ->label(__('Renews automatically'))
                    ->icon(function ($state) {
                        $state = boolval($state);

                        return $state ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle';
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label(__('View Details')),
                    Tables\Actions\Action::make('change-plan')
                        ->label(__('Change Plan'))
                        ->icon('heroicon-o-rocket-launch')
                        ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('change-plan', ['record' => $record->uuid]))
                        ->visible(fn (Subscription $record, PlanManager $planManager): bool => $record->status === SubscriptionStatus::ACTIVE->value && $planManager->isPlanChangeable($record->plan)),
                    Tables\Actions\Action::make('cancel')
                        ->label(__('Cancel Subscription'))
                        ->icon('heroicon-m-x-circle')
                        ->visible(fn (Subscription $record): bool => ! $record->is_canceled_at_end_of_cycle && $record->status === SubscriptionStatus::ACTIVE->value)
                        ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('cancel', ['record' => $record->uuid])),
                    Tables\Actions\Action::make('discard-cancellation')
                        ->label(__('Discard Cancellation'))
                        ->icon('heroicon-m-x-circle')
                        ->action(function ($record, DiscardSubscriptionCancellationActionHandler $handler) {
                            $handler->handle($record);
                        })->visible(fn (Subscription $record): bool => $record->is_canceled_at_end_of_cycle && $record->status === SubscriptionStatus::ACTIVE->value),
                ]),
            ])
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [
            'usages' => UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'change-plan' => Pages\ChangeSubscriptionPlan::route('/{record}/change-plan'),
            'cancel' => Pages\CancelSubscription::route('/{record}/cancel'),
            'confirm-cancellation' => Pages\ConfirmCancelSubscription::route('/{record}/confirm-cancellation'),
            'add-discount' => Pages\AddDiscount::route('/{record}/add-discount'),
            'paddle.update-payment-details' => Pages\PaymentProviders\Paddle\PaddleUpdatePaymentDetails::route('/paddle/update-payment-details'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->user()->id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canUpdate(Model $record): bool
    {
        return false;
    }

    public static function canUpdateAny(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;  // we want to ignore the default permission check (from the policy) and allow all users to view their own subscriptions
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('Subscription Details'))
                    ->description(__('View details about your subscription.'))
                    ->schema([
                        ViewEntry::make('status')
                            ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::PAST_DUE->value)
                            ->view('filament.common.infolists.entries.warning', [
                                'message' => __('Your subscription is past due. Please update your payment details.'),
                            ]),
                        TextEntry::make('plan.name'),
                        TextEntry::make('price')->formatStateUsing(function (string $state, $record) {
                            $interval = $record->interval->name;
                            if ($record->interval_count > 1) {
                                $interval = __('every ').$record->interval_count.' '.__(str()->of($record->interval->name)->plural()->toString());
                            }

                            return money($state, $record->currency->code).' / '.$interval;
                        }),
                        TextEntry::make('price_per_unit')
                            ->visible(fn (Subscription $record): bool => $record->price_type === PlanPriceType::USAGE_BASED_PER_UNIT->value && $record->price_per_unit !== null)
                            ->formatStateUsing(function (string $state, $record) {
                                return money($state, $record->currency->code).' / '.__($record->plan->meter->name);
                            }),
                        TextEntry::make('price_tiers')
                            ->visible(fn (Subscription $record): bool => in_array($record->price_type, [PlanPriceType::USAGE_BASED_TIERED_VOLUME->value, PlanPriceType::USAGE_BASED_TIERED_GRADUATED->value]) && $record->price_tiers !== null)
                            ->getStateUsing(function (Subscription $record) {
                                $start = 0;
                                $unitMeterName = $record->plan->meter->name;
                                $currencyCode = $record->currency->code;
                                $output = '';
                                $startingPhrase = __('From');
                                foreach ($record->price_tiers as $tier) {
                                    $output .= $startingPhrase.' '.$start.' - '.$tier[PlanPriceTierConstants::UNTIL_UNIT].' '.__(str()->plural($unitMeterName)).' → '.money($tier[PlanPriceTierConstants::PER_UNIT], $currencyCode).' / '.__($unitMeterName);
                                    if ($tier[PlanPriceTierConstants::FLAT_FEE] > 0) {
                                        $output .= ' + '.money($tier[PlanPriceTierConstants::FLAT_FEE], $currencyCode);
                                    }
                                    $start = intval($tier[PlanPriceTierConstants::UNTIL_UNIT]) + 1;
                                    $output .= '<br>';

                                    if ($record->price_type === PlanPriceType::USAGE_BASED_TIERED_GRADUATED->value) {
                                        $startingPhrase = __('Next');
                                    }
                                }

                                return new HtmlString($output);
                            }),
                        TextEntry::make('ends_at')->dateTime(config('app.datetime_format'))->label(__('Next Renewal'))->visible(fn (Subscription $record): bool => ! $record->is_canceled_at_end_of_cycle),
                        TextEntry::make('status')
                            ->formatStateUsing(fn (string $state, SubscriptionStatusMapper $mapper): string => $mapper->mapForDisplay($state)),
                        TextEntry::make('is_canceled_at_end_of_cycle')
                            ->label(__('Renews automatically'))
                            ->icon(
                                function ($state) {
                                    $state = boolval($state);

                                    return $state ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle';
                                })
                            ->formatStateUsing(
                                function ($state) {
                                    return boolval($state) ? __('No') : __('Yes');
                                }),
                    ]),
                Section::make(__('Discount Details'))
                    ->hidden(fn (Subscription $record): bool => $record->discounts->isEmpty() ||
                        ($record->discounts[0]->valid_until !== null && $record->discounts[0]->valid_until < now())
                    )
                    ->description(__('View details about your discount.'))
                    ->schema([
                        TextEntry::make('discounts.amount')
                            ->label(__('Discount Amount'))
                            ->formatStateUsing(function (string $state, $record) {
                                if ($record->discounts[0]->type === DiscountConstants::TYPE_PERCENTAGE) {
                                    return $state.'%';
                                }

                                return money($state, $record->discounts[0]->code);
                            }),

                        TextEntry::make('discounts.valid_until')
                            ->dateTime(config('app.datetime_format'))
                            ->visible(fn (Subscription $record): bool => $record->discounts[0]->valid_until !== null)
                            ->label(__('Valid Until')),
                    ]),

            ]);
    }

    public static function isDiscovered(): bool
    {
        return app()->make(ConfigManager::class)->get('app.customer_dashboard.show_subscriptions', true);
    }
}
