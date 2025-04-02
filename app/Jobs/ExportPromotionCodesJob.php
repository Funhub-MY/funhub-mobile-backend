<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use App\Models\PromotionCode;

class ExportPromotionCodesJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	protected $promotionCodeGroup;
	protected $promotionCodeIds;
	protected $userId;

	public function __construct($promotionCodeGroup = null, array $promotionCodeIds = [], $userId)
	{
		$this->promotionCodeGroup = $promotionCodeGroup;
		$this->promotionCodeIds = $promotionCodeIds;
		$this->userId = $userId;
	}

	public function handle()
	{
		$timestamp = date('Y-m-d_H-i-s');
		$filename = 'promotion-codes-' . $timestamp . '.csv';
		$filePath = $filename;

		$defaultDisk = config('filesystems.default');
		$disk = in_array($defaultDisk, ['s3', 's3_public']) ? 's3_public' : 'public';

		Excel::store(
			new \App\Exports\PromotionCodesExport($this->promotionCodeGroup, $this->promotionCodeIds),
			$filePath,
			$disk,
			\Maatwebsite\Excel\Excel::CSV,
			[
				'visibility' => 'public',
			]
		);

		// Generate appropriate URL based on disk
		$downloadUrl = $disk === 's3_public'
			? Storage::disk('s3_public')->temporaryUrl(
				$filePath,
				now()->addHour(),
				['ResponseContentDisposition' => 'attachment; filename="' . $filename . '"']
			)
			: Storage::disk('public')->url($filePath);

		Log::info('[ExportPromotionCodesJob] Export file created', [
			'disk' => $disk,
			'file_path' => $filePath,
			'download_url' => $downloadUrl,
		]);

		Notification::make()
			->title('Export Ready')
			->body('Your promotion codes export is ready.')
			->actions([
				\Filament\Notifications\Actions\Action::make('download')
					->label('Download')
					->url($downloadUrl),
			])
			->success()
			->sendToDatabase(\App\Models\User::find($this->userId));

		Log::info('[ExportPromotionCodesJob] Promotion codes Export completed', [
			'file_path' => $filePath,
			'download_url' => $downloadUrl,
		]);
	}
}