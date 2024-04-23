<?php

namespace App\Console\Commands;

use App\Models\AutocompleteSuggestion;
use App\Models\CityName;
use App\Models\SearchKeyword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateAutocompleteSuggestionsCommand extends Command
{
    protected $signature = 'autocomplete:generate';
    protected $description = 'Generate autocomplete suggestions based on CityNames and SearchKeyword';

    public function handle()
    {
        $cityNames = CityName::all();
        $searchKeywords = SearchKeyword::where('blacklisted', false)->get();

        $generatedSuggestionsCount = 0;

        // Match each city name with keyword
        foreach ($cityNames as $cityName) {
            foreach ($searchKeywords as $searchKeyword) {
                $suggestion = $cityName->name . ' ' . $searchKeyword->keyword;
                AutocompleteSuggestion::firstOrCreate(
                    [
                        'suggestion' => $suggestion,
                        'city_id' => $cityName->city->id,
                        'keyword_id' => $searchKeyword->id,
                    ],
                    [
                        'city_name' => $cityName->name,
                        'city_standardised_name' => $cityName->city->name,
                        'keyword' => $searchKeyword->keyword,
                    ]
                );

                $this->info('Synced: ' . $suggestion);
                $generatedSuggestionsCount++;
            }
        }

        $this->info('Generated ' . $generatedSuggestionsCount . ' suggestions');
        Log::info('[GenerateAutocompleteSuggestionsCommand] Generated ' . $generatedSuggestionsCount . ' suggestions');

        // Call the `searchable()` method to update the search index
        AutocompleteSuggestion::all()->searchable();
    }
}
