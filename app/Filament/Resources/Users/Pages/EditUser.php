<?php

namespace App\Filament\Resources\Users\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\Article;
use App\Models\Location;
use App\Models\LocationRating;
use App\Models\User;
use App\Models\UserBlock;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

	protected function getHeaderActions(): array
	{
		return [
			DeleteAction::make()
				->requiresConfirmation()
				->action(function (array $data) {
					$user = $this->record;

					// Archive all articles by this user
					$user->articles()->update([
						'status' => Article::STATUS_ARCHIVED
					]);

					// Remove user from any UserBlock
					UserBlock::where('blockable_id', $user->id)
						->where('blockable_type', User::class)
						->delete();

					// Delete user's article ranks
					$user->articleRanks()->delete();

					// Delete user's location ratings
					$locationRatings = LocationRating::where('user_id', $user->id)->get();
					$locationIdsNeedRecalculateRatings = $locationRatings->pluck('location_id')->toArray();

					// Recalculate location average ratings
					Location::whereIn('id', $locationIdsNeedRecalculateRatings)->get()->each(function ($location) {
						$location->average_ratings = $location->ratings()->avg('rating');
						$location->save();
					});

					// Remove user_id from scout index
					$user->unsearchable();

					// Add a new record for account deletion for backup purposes
					$user->userAccountDeletion()->create([
						'reason' => 'Deleted from admin panel',
						'name' => $user->name,
						'username' => $user->username,
						'email' => $user->email,
						'phone_no' => $user->phone_no,
						'phone_country_code' => $user->phone_country_code,
					]);

					Log::info('User Account Deleted from Admin Portal', ['user_id' => $user->id]);

					// Unset user's personal information to allow re-registration
					$user->name = null;
					$user->username = null;
					$user->phone_no = null;
					$user->phone_country_code = null;
					$user->email = null;
					$user->password = null;
					$user->status = User::STATUS_ARCHIVED;
					$user->google_id = null;
					$user->facebook_id = null;
					$user->apple_id = null;
					$user->save();

					$this->notify('success', 'User account deleted successfully.');

					// Redirect to the users listing page
					return redirect()->to(UserResource::getUrl());
				}),
		];
	}
}
