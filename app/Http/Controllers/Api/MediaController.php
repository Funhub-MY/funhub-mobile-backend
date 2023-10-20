<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    const userUploadsCollection = 'signed_uploads';

    /**
     * Get Signed URL Upload Media (photos/videos)
     * Must only call one per file. If multiple files, call multiple times. URL generated you need to PUT a binary file to it.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Media
     * @urlParam filename string required The filename of the media to be uploaded, filename must only contain alphabets and numbers. Example: my-photo.jpg
     *
     * @response scenario=success {
     * "id": "1619114400-608b7a10a3b3d",
     * "url": "https://s3-ap-southeast-1.amazonaws.com/your-bucket-name/user_uploads/my-photo.jpg?AWSAccessKeyId=your-access-key-id&Expires=1619114400&Signature=your-signature"
     * }
     * @response scenario=failed {
     * "message": "Only applicable for S3 Storage",
     * "url": null
     * }
     */
    public function getSignedUploadLink(Request $request)
    {
       // if s3 filesystems is default, create temporary upload url with s3
       if (config('filesystems.default') == 's3') {
            // reject file name if non alphabets letters exists
            if (!preg_match('/^[a-zA-Z0-9-_\.]+$/', $request->get('filename'))) {
                return response()->json([
                    'message' => 'Invalid filename, only alphabets letters or numbers are allowed',
                    'url' => null,
                ]);
            }

           $filename = $request->get('filename');
           $newFilename = Carbon::now()->timestamp . '_' . Str::random(4) .strtolower($filename);
           $uploadId = Carbon::now()->timestamp . '-' . uniqid();
           $fullPath =  self::userUploadsCollection . '/' . $newFilename;

           cache()->put($uploadId, $newFilename, now()->addMinutes(60));

            $data = Storage::temporaryUploadUrl(
                $fullPath,
                now()->addMinutes(20)
            );
            return response()->json([
                'upload_id' => $uploadId,
                'url' => $data['url'],
            ]);
       }

       return response()->json([
            'message' => 'Only applicable for S3 Storage',
            'url' => null,
       ]);
    }

    /**
     * Upload Media Complete
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Media
     * @bodyParam upload_ids string required The upload_ids from get signed upload link, can be multiple ids in a comma separated. Example: 1619114400-608b7a10a3b3d,1619114400-608b7a10a3b3e
     * @bodyParam is_cover boolean optional The is_cover flag, default false. Example: true
     * @response scenario=success {
     * "message": "Success",
     * "uploaded": [
     * {
     * "id": 1,
     * "name": "1619114400-608b7a10a3b3d_my-photo.jpg",
     * "url": "https://your-bucket-name.s3-ap-southeast-1.amazonaws.com/user_uploads/1619114400-608b7a10a3b3d_my-photo.jpg",
     * "size": 12345,
     * "type": "image/jpeg"
     * },
     * ]
     * }
     */
    public function postUploadMediaComplete(Request $request)
    {
        // only applicable if storage is s3
        if (config('filesystems.default') != 's3') {
            return response()->json([
                'message' => 'Only applicable for S3 Storage',
                'media_ids' => null,
            ]);
        }

        $this->validate($request, [
            'upload_ids' => 'required'
        ]);

        $user = $request->user();
        $uploadIds = explode(',', $request->get('upload_ids'));
        $medias = [];

        foreach($uploadIds as $uploadId) {
            $filename = cache()->get($uploadId);

            if($filename) {
                // move file to actual spatie collection for user_uploads
                $collection = self::userUploadsCollection;
                $fullPath =  $collection . '/' . $filename;

                if (!$request->has('is_cover')) { // default false if not provided
                    $request->merge(['is_cover' => false]);
                }

                try {
                    // user add media from Storage::file($fullPath) to collection "user_uploads"
                    $file = $user->addMediaFromDisk($fullPath, 's3')
                        ->toMediaCollection(User::USER_UPLOADS);
                } catch (\Exception $e) {
                    Log::error('[MediaController] Error completing file upload to user_uploads: ' . $e->getMessage(), [
                        'uploadId' => $uploadId,
                    ]);
                    return response()->json([
                        'message' => 'Error completing upload file for upload ID: ' . $uploadId . ' ' . $e->getMessage(),
                        'media_ids' => null,
                    ]);
                }

                // update image size if its an image
                if (str_contains($file->mime_type, 'image')) {
                    // if image always set is_cover, else do nothing
                    $file->setCustomProperty('is_cover', $request->is_cover);

                   try {
                        $imageSize = getimagesize($file->getFullUrl());
                        // save the media width and height
                        Media::withoutEvents(function () use ($file, $imageSize) {
                            $file->setCustomProperty('width', $imageSize[0]);
                            $file->setCustomProperty('height', $imageSize[1]);
                            $file->save();
                        });
                   } catch (\Exception $ex) {
                       Log::error('[MediaController] Error getting image size: ' . $ex->getMessage(), [
                           'uploadId' => $uploadId,
                           'media_id' => $file->id,
                       ]);
                   }
                }

                $medias[] = [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'url' => $file->getUrl(),
                    'size' => $file->size,
                    'type' => $file->mime_type,
                ];

                // delete temporary file from signed_uploads temporary
                Storage::disk('s3')->delete($fullPath);
            } else {
                // filename not found from uploadId
                Log::error('[MediaController] Filename not found from uploadId: ' . $uploadId);
            }
        }

        return response()->json([
            'message' => 'Success',
            'uploaded' => $medias,
        ]);
    }
}
