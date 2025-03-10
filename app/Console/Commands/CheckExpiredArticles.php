<?php

namespace App\Console\Commands;

use App\Models\ArticleExpired;
use App\Models\ExpiredKeyword;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Article;
use Illuminate\Support\Facades\Log;

class CheckExpiredArticles extends Command
{
	protected $signature = 'articles:check-expired';
	protected $description = 'Check articles for expiry dates and update status';

	public function handle()
	{
		$this->info("Starting article expiry check...");
		Log::info("[Check Expired Articles] Starting article expiry check...");

		// Get active keywords from the database
		$keywords = ExpiredKeyword::pluck('keyword')->toArray();

		if (empty($keywords)) {
			$this->warn("[Check Expired Articles] No active keywords found!");
			Log::warning("[Check Expired Articles] No active keywords found!");
			return;
		}

		$totalProcessed = 0;
		$totalUpdated = 0;

		Article::where('is_expired', false)
			->doesntHave('articleExpired')
			->where(function ($query) use ($keywords) {
				$firstKeyword = true;
				foreach ($keywords as $keyword) {
					if ($firstKeyword) {
						$query->where(function ($q) use ($keyword) {
							$q->where('title', 'like', '%' . $keyword . '%')
								->orWhere('body', 'like', '%' . $keyword . '%');
						});
						$firstKeyword = false;
					} else {
						$query->orWhere(function ($q) use ($keyword) {
							$q->where('title', 'like', '%' . $keyword . '%')
								->orWhere('body', 'like', '%' . $keyword . '%');
						});
					}
				}
			})
			->chunk(100, function ($articles) use (&$totalProcessed, &$totalUpdated) {
				$this->info("Processing chunk of articles...");

				foreach ($articles as $article) {
					$totalProcessed++;
					$isExpired = $this->checkForExpiry($article);

					// Record the check in articles_expired table
					ArticleExpired::create([
						'article_id' => $article->id,
						'processed_time' => Carbon::now(),
						'is_expired' => $isExpired
					]);

					if ($isExpired) {
						$article->update(['is_expired' => true]);
						$totalUpdated++;
					}
				}
			});

		$this->info("Completed processing!");
		$this->info("Total articles processed: {$totalProcessed}");
		$this->info("Total articles updated: {$totalUpdated}");
		Log::info("[Check Expired Articles] Completed processing!");
		Log::info("[Check Expired Articles] Total articles processed: {$totalProcessed}");
		Log::info("[Check Expired Articles] Total articles updated: {$totalUpdated}");
	}

	private function checkForExpiry($article)
	{
		$content = $article->title . ' ' . $article->body;

		$currentDate = Carbon::now();

		// Scenario 1: Check dates in title/content
		$dates = $this->extractDates($content);

		if (!empty($dates)) {
			// Get the latest date if multiple dates exist
			$latestDate = $this->getLatestDate($dates);

			// Check if the latest date is more than 10 days old AND in the past
			if ($latestDate->startOfDay()->isPast() &&
				$latestDate->startOfDay()->diffInDays($currentDate->startOfDay()) > 10) {
				Log::info("[Check Expired Articles] Article ID {$article->id} marked as expired due to date being more than 10 days old: " . $latestDate->toDateString());
				return true;
			}
		}

		// Scenario 2: Check for expired keywords with dates
		$keywords = ExpiredKeyword::pluck('keyword')->toArray();
		foreach ($keywords as $keyword) {
			if (stripos($content, $keyword) !== false) {
				// If keyword is found and there's a date more than 10 days old
				if (!empty($dates)) {
					$latestDate = $this->getLatestDate($dates);
					if ($latestDate->startOfDay()->isPast() &&
						$latestDate->startOfDay()->diffInDays($currentDate->startOfDay()) > 10) {
						Log::info("[Check Expired Articles] Article ID {$article->id} marked as expired due to date being more than 10 days old: " . $latestDate->toDateString());
						return true;
					}
				}
			}
		}

		return false;
	}

	private function getLatestDate($dates)
	{
		if (empty($dates)) {
			return null;
		}

		$latestDate = $dates[0];
		foreach ($dates as $date) {
			if ($date->gt($latestDate)) {
				$latestDate = $date;
			}
		}
		return $latestDate;
	}

	private function extractDates($content)
	{
		$dates = [];

		// Match Chinese format with year (e.g., 2024年1月1日)
		preg_match_all('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $content, $matches);
		for ($i = 0; $i < count($matches[0]); $i++) {
			try {
				$date = Carbon::create($matches[1][$i], $matches[2][$i], $matches[3][$i]);
				$dates[] = $date;
			} catch (\Exception $e) {
				continue;
			}
		}

		// Match dd/mm/yyyy format (e.g. 12/10/2024)
		preg_match_all('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', $content, $matches);
		for ($i = 0; $i < count($matches[0]); $i++) {
			try {
				$date = Carbon::createFromFormat('d/m/Y', $matches[1][$i] . '/' . $matches[2][$i] . '/' . $matches[3][$i]);
				$dates[] = $date;
			} catch (\Exception $e) {
				continue;
			}
		}

		// Match dd-mm-yyyy format (e.g. 12-10-2024)
		preg_match_all('/\b(\d{1,2})-(\d{1,2})-(\d{4})\b/', $content, $matches);
		for ($i = 0; $i < count($matches[0]); $i++) {
			try {
				$date = Carbon::createFromFormat('d-m-Y', $matches[1][$i] . '-' . $matches[2][$i] . '-' . $matches[3][$i]);
				$dates[] = $date;
			} catch (\Exception $e) {
				continue;
			}
		}

		return $dates;
	}
}