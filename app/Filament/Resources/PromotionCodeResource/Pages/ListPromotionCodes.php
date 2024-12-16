<?php

namespace App\Filament\Resources\PromotionCodeResource\Pages;

use App\Filament\Resources\PromotionCodeResource;
use App\Models\PromotionCode;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListPromotionCodes extends ListRecords
{
    protected static string $resource = PromotionCodeResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('generateCodes')
                ->label('Generate Codes')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('number_of_codes')
                        ->label('Number of Codes to Generate')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(1),
                        
                    Forms\Components\TagsInput::make('tags')
                        ->separator(',')
                        ->suggestions([
                            'promotion',
                            'event',
                            'seasonal',
                            'special',
                        ]),

                    Forms\Components\MorphToSelect::make('rewardable')
                        ->label('Reward Type')
                        ->types([
                            Forms\Components\MorphToSelect\Type::make(Reward::class)
                                ->titleColumnName('name')
                                ->modifyOptionsQueryUsing(function ($query) {
                                    return $query->select('rewards.id', 'rewards.name')
                                        ->whereIn('id', function($subquery) {
                                            $subquery->selectRaw('MIN(id)')
                                                ->from('rewards')
                                                ->groupBy('name');
                                        });
                                }),
                            Forms\Components\MorphToSelect\Type::make(RewardComponent::class)
                                ->titleColumnName('name'),
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Reward Quantity')
                        ->helperText('How many rewards to give when code is redeemed')
                        ->numeric()
                        ->default(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    return DB::transaction(function () use ($data) {
                        $numberOfCodes = $data['number_of_codes'];
                        unset($data['number_of_codes']);

                        $codes = [];
                        
                        for ($i = 0; $i < $numberOfCodes; $i++) {
                            $codeData = array_merge($data, [
                                'code' => PromotionCode::generateUniqueCode(),
                            ]);
                            
                            $code = PromotionCode::create($codeData);

                            Log::info('data', $data);
                            
                            if (!empty($data)) {
                                $rewardableType = $data['rewardable_type'];
                                $rewardableId = $data['rewardable_id'];
                                $quantity = $data['quantity'];

                                Log::info('rewardable', [
                                    'rewardableType' => $rewardableType,
                                    'rewardableId' => $rewardableId,
                                    'quantity' => $quantity
                                ]);

                                if ($rewardableType === Reward::class) {
                                    $code->reward()->attach($rewardableId, ['quantity' => $quantity]);
                                    Log::info('attach reward', [
                                        'rewardableType' => $rewardableType,
                                        'rewardableId' => $rewardableId,
                                        'quantity' => $quantity
                                    ]);
                                } elseif ($rewardableType === RewardComponent::class) {
                                    $code->rewardComponent()->attach($rewardableId, ['quantity' => $quantity]);
                                    Log::info('attach rewardComponent', [
                                        'rewardableType' => $rewardableType,
                                        'rewardableId' => $rewardableId,
                                        'quantity' => $quantity
                                    ]);

                                } else {
                                    Log::info('rewardable type not found', [
                                        'rewardableType' => $rewardableType,
                                        'reward' => Reward::class,
                                        'rewardComponent' => RewardComponent::class
                                    ]);
                                }
                            }
                            
                            $codes[] = $code;
                        }

                        return $codes;
                    });
                })
                ->after(function () {
                    Notification::make()
                        ->title('Success')
                        ->body('Promotion codes generated successfully.')
                        ->success()
                        ->send();
                })
        ];
    }
}
