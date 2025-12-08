<div class="space-y-4">
    @foreach($files as $file)
        <div class="border rounded-lg p-4 bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="font-medium text-sm text-gray-900">
                        {{ $file->getCustomProperty('field_key') ?? 'File' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $file->name }} ({{ number_format($file->size / 1024, 2) }} KB)
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $file->mime_type }}
                    </p>
                </div>
                <div>
                    <a href="{{ $file->getUrl() }}" target="_blank" 
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View
                    </a>
                </div>
            </div>
        </div>
    @endforeach
</div>

