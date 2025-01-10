<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Article;
use Illuminate\Support\Facades\Log;

class CheckExpiredArticles extends Command
{
	protected $signature = 'articles:check-expired';
	protected $description = 'Check articles for expiry dates and update status';

	private $keywords = [
		'演唱会', 'Concert', '音乐会', 'Fiesta', '电影', 'Movie',
		'新闻', 'News', '娱乐', 'Entertainment', '八卦', 'Gossip',
		'节', 'Festive', 'Festival'
	];

	public function handle()
	{
		$this->info("Starting article expiry check...");
		Log::info("Starting article expiry check...");

		$totalProcessed = 0;
		$totalUpdated = 0;

		Article::where('is_expired', false)
			->where(function ($query) {
				$firstKeyword = true;
				foreach ($this->keywords as $keyword) {
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
			->orderBy('created_at', 'desc')  // Order by latest first
			->chunk(100, function ($articles) use (&$totalProcessed, &$totalUpdated) {
				$this->info("Processing chunk of articles...");

				foreach ($articles as $article) {
					$totalProcessed++;

					if ($this->checkForExpiry($article)) {
						$article->update(['is_expired' => true]);
						$totalUpdated++;
					}
				}

				$this->info("Processed {$totalProcessed} articles so far...");
			});

		$this->info("Completed processing!");
		$this->info("Total articles processed: {$totalProcessed}");
		$this->info("Total articles updated: {$totalUpdated}");
		Log::info("Completed processing!");
		Log::info("Total articles processed: {$totalProcessed}");
		Log::info("Total articles updated: {$totalUpdated}");
	}

	private function checkForExpiry($article)
	{
		$content = $article->title . ' ' . $article->body;

		$dates = $this->extractDates($content);

		if (empty($dates)) {
			return false;
		}

		$currentDate = Carbon::now();

		Log::info("Extracted dates for article ID {$article->id}:");
		foreach ($dates as $date) {
			Log::info($date->toDateString());
		}

		// Check if any extracted date is in the past
		foreach ($dates as $date) {
			if ($date->startOfDay()->lt($currentDate->startOfDay())) {
				Log::info("Article ID {$article->id} marked as expired due to date: " . $date->toDateString());
				return true;
			}
		}

		return false;
	}

	private function extractDates($content)
	{
		$dates = [];

		// Match Chinese format with year FIRST (e.g., 2024年1月1日)
		preg_match_all('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $content, $matches);
		for ($i = 0; $i < count($matches[0]); $i++) {
			try {
				$date = Carbon::create($matches[1][$i], $matches[2][$i], $matches[3][$i]);
				$dates[] = $date;
			} catch (\Exception $e) {
				continue;
			}
		}

		// Match Chinese format without year (e.g., 12月10日)
//		preg_match_all('/(?<!\d{4}年)(\d{1,2})月(\d{1,2})日/', $content, $matches);
//		for ($i = 0; $i < count($matches[0]); $i++) {
//			try {
//				$date = Carbon::create(date('Y'), $matches[1][$i], $matches[2][$i]);
//				$dates[] = $date;
//			} catch (\Exception $e) {
//				continue;
//			}
//		}

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

		// Match dd/mm format (e.g., 12/10, assume current year)
//		preg_match_all('/\b(\d{1,2})\/(\d{1,2})\b(?!\/\d{4})/', $content, $matches);
//		for ($i = 0; $i < count($matches[0]); $i++) {
//			try {
//				$date = Carbon::createFromFormat('d/m/Y', $matches[1][$i] . '/' . $matches[2][$i] . '/' . date('Y'));
//				$dates[] = $date;
//			} catch (\Exception $e) {
//				continue;
//			}
//		}

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

		// Match dd-mm format (e.g., 12-10, assume current year)
//		preg_match_all('/\b(\d{1,2})-(\d{1,2})\b(?!-\d{4})/', $content, $matches);
//		for ($i = 0; $i < count($matches[0]); $i++) {
//			try {
//				$date = Carbon::createFromFormat('d-m-Y', $matches[1][$i] . '-' . $matches[2][$i] . '-' . date('Y'));
//				$dates[] = $date;
//			} catch (\Exception $e) {
//				continue;
//			}
//		}

		return $dates;
	}
}