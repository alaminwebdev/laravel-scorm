@extends('layouts.app')
@section('title', $package->title . ' - SCORM Content')
@section('content')
    <div class="flex h-screen bg-gray-100">

        <!-- Sidebar -->
        <div class="w-80 bg-white shadow-lg border-r border-gray-200 overflow-y-auto">
            <div class="p-4 border-b border-gray-200">
                <h1 class="text-lg font-bold text-gray-800 truncate">{{ $package->title }}</h1>
                <p class="text-sm text-gray-500">SCORM {{ $package->version }}</p>
            </div>

            <nav class="p-4">
                <ul class="space-y-1">
                    @foreach ($scos as $sco)
                        @include('scorm.partials.sco-item', ['sco' => $sco, 'level' => 0, 'package' => $package])
                    @endforeach
                </ul>
            </nav>
        </div>

        <!-- Player Area -->
        <div class="flex-1 flex flex-col">
            <header class="bg-white shadow-sm border-b border-gray-200 p-4">
                <div class="flex justify-between items-center">
                    <h2 id="current-sco-title" class="text-xl font-semibold text-gray-800">{{ $package->title }}</h2>
                    <div class="flex space-x-2">
                        <button id="prev-btn" class="bg-gray-200 text-gray-700 px-4 py-2 rounded cursor-not-allowed" disabled>Previous</button>
                        <button id="next-btn" class="bg-gray-200 text-gray-700 px-4 py-2 rounded cursor-not-allowed" disabled>Next</button>
                    </div>
                </div>
            </header>
            <main class="flex-1 bg-white">
                <div id="player-container" class="h-full">
                    <iframe id="scormPlayerFrame" src="about:blank" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
                </div>
            </main>
        </div>
    </div>

    <!-- Dynamic route pattern -->
    <div id="route-patterns" data-content-route="{{ route('scorm.content', ['package' => $package->id, 'path' => '--PATH--']) }}" style="display:none;"></div>

    {{-- Version-specific API --}}
    @if ($package->version == '1.2')
        <script>
            window.API = {
                LMSInitialize: () => "true",
                LMSFinish: () => "true",
                LMSGetValue: () => "",
                LMSSetValue: () => "true",
                LMSCommit: () => "true",
                LMSGetLastError: () => 0,
                LMSGetErrorString: () => "No error",
                LMSGetDiagnostic: () => ""
            };
        </script>
    @elseif($package->version == '2004')
        <script>
            window.API_1484_11 = {
                Initialize: () => "true",
                Terminate: () => "true",
                GetValue: () => "",
                SetValue: () => "true",
                Commit: () => "true",
                GetLastError: () => 0,
                GetErrorString: () => "No error",
                GetDiagnostic: () => ""
            };
        </script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const frame = document.getElementById('scormPlayerFrame');
            const routePattern = document.getElementById('route-patterns').dataset.contentRoute;

            const scoEls = Array.from(document.querySelectorAll('[data-sco-id]'));
            const scos = scoEls.map(el => ({
                id: el.dataset.scoId,
                el,
                launch: el.dataset.launch
            }));

            let currentIndex = -1;

            function buildScormContentUrl(path) {
                if (!path) return '';

                // Split by '?' to separate path and query
                const [pathname, query = ''] = path.split('?');

                // Encode only path segments
                const safePath = pathname.split('/').map(encodeURIComponent).join('/');

                // Recombine with query (unchanged)
                const finalPath = query ? safePath + '?' + query : safePath;

                return routePattern.replace('--PATH--', finalPath);
            }

            function loadSco(path) {
                frame.src = buildScormContentUrl(path);
                // Wait for iframe to finish loading DOM
                frame.onload = () => {
                    const doc = frame.contentDocument || frame.contentWindow.document;

                    // Ensure DOM is fully parsed
                    if (doc.readyState === 'complete' || doc.readyState === 'interactive') {
                        console.log("SCO content loaded and ready");
                        // Base CSS, scripts, and SCORM functions inside iframe are now fully available
                    } else {
                        doc.addEventListener('DOMContentLoaded', () => {
                            console.log("SCO DOM fully parsed");
                        });
                    }
                };
            }

            const initialPath = "{{ $package->entry_point }}";
            if (initialPath) loadSco(initialPath);

            scos.forEach((item, i) => {
                item.el.addEventListener('click', e => {
                    e.stopPropagation();
                    scos.forEach(s => s.el.classList.remove('bg-blue-50', 'text-blue-700', 'border-blue-200'));
                    item.el.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
                    document.getElementById('current-sco-title').textContent =
                        item.el.querySelector('.sco-title')?.textContent?.trim() || '{{ $package->title }}';

                    currentIndex = i;
                    updateNavButtons();

                    loadSco(item.launch || initialPath);
                });
            });

            function updateNavButtons() {
                const prevBtn = document.getElementById('prev-btn');
                const nextBtn = document.getElementById('next-btn');
                prevBtn.disabled = currentIndex <= 0;
                nextBtn.disabled = currentIndex >= scos.length - 1;
                [prevBtn, nextBtn].forEach(btn => {
                    if (btn.disabled) {
                        btn.classList.add('cursor-not-allowed', 'bg-gray-200');
                        btn.classList.remove('cursor-pointer', 'bg-blue-600', 'text-white');
                    } else {
                        btn.classList.remove('cursor-not-allowed', 'bg-gray-200');
                        btn.classList.add('cursor-pointer', 'bg-blue-600', 'text-white');
                    }
                });
            }

            document.getElementById('prev-btn').addEventListener('click', () => {
                if (currentIndex > 0) scos[currentIndex - 1].el.click();
            });
            document.getElementById('next-btn').addEventListener('click', () => {
                if (currentIndex < scos.length - 1) scos[currentIndex + 1].el.click();
            });
        });
    </script>
@endsection
