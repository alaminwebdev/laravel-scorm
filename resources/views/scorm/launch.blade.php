{{-- @extends('layouts.app')

@section('title', $package->title)

@section('content')
    <div class="bg-white shadow-md rounded-lg p-4">
        <h1 class="text-2xl font-bold mb-4">{{ $package->title }}</h1>

        <!-- SCORM iframe -->
        <iframe src="{{ $launchPath }}" class="w-full h-[80vh] border rounded-md" frameborder="0" allowfullscreen></iframe>
    </div>

    <!-- Load correct SCORM JS -->
    <script src="{{ asset('js/' . $apiJs) }}"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize SCORM API with current SCO ID
            const scoId = @json($scoId);

            if ("API" in window) {
                API.init(scoId);
            }
            if ("API_1484_11" in window) {
                API_1484_11.init(scoId);
            }
        });
    </script>
@endsection --}}


@extends('layouts.app')

@section('title', $package->title)

@section('content')
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-gray-50 px-6 py-4 border-b">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">{{ $package->title }}</h1>
                    @if (isset($sco))
                        <p class="text-sm text-gray-600 mt-1">{{ $sco->title }}</p>
                        @if (isset($navigation))
                            <p class="text-xs text-gray-500 mt-1">
                                Item {{ $navigation['current'] }} of {{ $navigation['total'] }}
                            </p>
                        @endif
                    @endif
                </div>

                @if (isset($navigation))
                    <div class="flex space-x-2">
                        @if ($navigation['previous'])
                            <a href="{{ route('scorm.launch', ['id' => $package->id, 'sco' => $navigation['previous']->id]) }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm">
                                ← Previous
                            </a>
                        @endif

                        @if ($navigation['next'])
                            <a href="{{ route('scorm.launch', ['id' => $package->id, 'sco' => $navigation['next']->id]) }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm">
                                Next →
                            </a>
                        @else
                            <a href="{{ route('scorm.index') }}" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm">
                                Complete
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- SCORM Content -->
        <div class="p-1">
            @if (isset($launchPath) && $launchPath)
                <iframe src="{{ $launchPath }}" class="w-full h-screen border-0" frameborder="0" allowfullscreen title="SCORM Content"></iframe>
            @else
                <div class="text-center py-12">
                    <div class="text-red-600 text-lg">Unable to load SCORM content</div>
                    <a href="{{ route('scorm.index') }}" class="mt-4 inline-block px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        Return to SCORM Packages
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Load SCORM JS -->
    @if (isset($apiJs))
        <script src="{{ asset('js/' . $apiJs) }}"></script>
    @endif

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Simple SCORM initialization
            setTimeout(function() {
                if (typeof API !== 'undefined') {
                    API.init();
                }
                if (typeof API_1484_11 !== 'undefined') {
                    API_1484_11.init();
                }
            }, 1000);
        });
    </script>
@endsection
