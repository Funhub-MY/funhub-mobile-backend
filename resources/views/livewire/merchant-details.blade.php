<div class="flex min-h-screen items-center justify-center bg-gray-100 py-12 text-gray-900">
    <div class="container">

        <form
            style="width:100%"
            class="relative mx-auto space-y-4 rounded-2xl border border-gray-200 bg-white/50 p-8 shadow-2xl backdrop-blur-xl">
            @if (session()->has('message'))
                <div class="font-semibold p-4 rounded-lg mb-6 text-center bg-success-600 text-white">
                    {{ session('message') }}
                </div>
            @elseif (session()->has('error'))
                <div class="font-semibold p-4 rounded-lg mb-6 text-center bg-danger-600 text-white">
                    {{ session('error') }}
                </div>
            @endif
            {{ $this->form }}

        </form>
    </div>
</div>