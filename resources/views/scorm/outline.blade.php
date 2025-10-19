@extends('layouts.app')
@section('title', $package->title . ' - SCORM Content')
@section('content')
    <div class="space-y-6">
        <div class="flex h-screen mb-15 shadow-md rounded-lg bg-white p-6 border border-gray-200">
            <!-- Sidebar -->
            <div class="w-80 bg-white border-r border-gray-200 overflow-y-auto">
                <div class="p-4">
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
                        <!-- Initial welcome content -->
                        <div id="welcome-content" class="h-full flex flex-col items-center justify-center bg-gray-50 p-8">
                            <div class="text-center max-w-2xl">
                                <div class="w-24 h-24 mx-auto mb-6 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                                        </path>
                                    </svg>
                                </div>
                                <h2 class="text-2xl font-bold text-gray-800 mb-4">Welcome to {{ $package->title }}</h2>
                                <p class="text-gray-600 mb-6">Click on any lesson in the navigation panel to the left to begin your learning journey. Each lesson contains interactive content and assessments.</p>
                            </div>
                        </div>
                        <iframe id="scormPlayerFrame" src="about:blank" style="display: none;" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Dynamic route pattern -->
    <div id="route-patterns" data-content-route="{{ route('scorm.content', ['package' => $package->id, 'path' => '--PATH--']) }}" style="display:none;"></div>

    {{-- Standard SCORM API Implementation --}}
    @if ($package->version == '1.2')
        <script>
            const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
            // Standard SCORM 1.2 API Implementation
            window.API = {
                // Initialize the SCO - must be called first
                LMSInitialize: function(param) {
                    return this._makeRequest('initialize', {})
                        .then(response => response.success ? "true" : "false");
                },

                // Terminate the SCO - must be called last
                LMSFinish: function(param) {
                    return this._makeRequest('terminate', {})
                        .then(response => response.success ? "true" : "false");
                },

                // Get value from data model
                LMSGetValue: function(element) {
                    return this._makeRequest('getvalue', {
                            element: element
                        })
                        .then(response => response.value || "")
                        .catch(() => "");
                },

                // Set value in data model
                LMSSetValue: function(element, value) {
                    return this._makeRequest('setvalue', {
                            element: element,
                            value: value
                        })
                        .then(response => response.success ? "true" : "false");
                },

                // Save data to persistent storage
                LMSCommit: function(param) {
                    return this._makeRequest('commit', {})
                        .then(response => response.success ? "true" : "false");
                },

                // Error handling - standard SCORM error codes
                LMSGetLastError: function() {
                    return 0;
                },
                LMSGetErrorString: function(errorCode) {
                    return "No error";
                },
                LMSGetDiagnostic: function(errorCode) {
                    return "";
                },

                // Internal request handler
                _makeRequest: function(action, data) {
                    const scoId = getCurrentScoId();
                    if (!scoId) return Promise.reject('No SCO ID available');

                    const routes = {
                        initialize: "{{ route('scorm.tracking.initialize', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        terminate: "{{ route('scorm.tracking.terminate', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        getvalue: "{{ route('scorm.tracking.getvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        setvalue: "{{ route('scorm.tracking.setvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        commit: "{{ route('scorm.tracking.commit', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}"
                    };

                    const url = routes[action].replace('--SCO_ID--', scoId);
                    const options = {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    };

                    if (action === 'getvalue') {
                        options.method = 'GET';
                    } else {
                        options.method = 'POST';
                        options.body = JSON.stringify(data);
                    }

                    return fetch(url, options)
                        .then(response => response.ok ? response.json() : Promise.reject('Network error'))
                        .catch(error => {
                            console.warn('SCORM API Error:', error);
                            throw error;
                        });
                }
            };

            // Quiz Handling - Only store actual quiz questions
            window.RecordQuestion = async function(id, text, type, learnerResponse, correctAnswer, wasCorrect, objectiveId) {
                const scoId = getCurrentScoId();
                if (!scoId) return;
                await delay(100);
                // Only store if it's a real quiz question (not tracking data)
                fetch("{{ route('scorm.tracking.interaction', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        interaction_id: id,
                        type: type,
                        description: text,
                        learner_response: learnerResponse,
                        correct_response: correctAnswer,
                        result: wasCorrect ? 'correct' : 'incorrect',
                        weighting: 1.0,
                        latency: 0,
                        objective_id: objectiveId
                    })
                }).catch(console.error);
            };

            window.RecordTest = async function(score) {
                const scoId = getCurrentScoId();
                if (!scoId) return;

                const lesson_status = score >= 33 ? 'passed' : 'failed';

                // Update tracking with final score
                await window.API.LMSSetValue('cmi.core.score.raw', score.toString());
                await delay(50);
                await window.API.LMSSetValue('cmi.core.lesson_status', lesson_status);
                await delay(50);
                await window.API.LMSCommit();

                console.log(`Test completed: ${score}% - Status: ${lesson_status}`);
            };
        </script>
    @else
        <script>
            const delay2004 = ms => new Promise(resolve => setTimeout(resolve, ms));

            // Standard SCORM 2004 API Implementation
            window.API_1484_11 = {
                // Initialize the SCO
                Initialize: function() {
                    return this._makeRequest('initialize', {})
                        .then(response => response.success ? "true" : "false");
                },

                // Terminate the SCO
                Terminate: function() {
                    return this._makeRequest('terminate', {})
                        .then(response => response.success ? "true" : "false");
                },

                // Get value from data model
                GetValue: function(element) {
                    return this._makeRequest('getvalue', {
                            element: element
                        })
                        .then(response => response.value || "")
                        .catch(() => "");
                },

                // Set value in data model
                SetValue: function(element, value) {
                    return this._makeRequest('setvalue', {
                            element: element,
                            value: value
                        })
                        .then(response => response.success ? "true" : "false");
                },

                // Commit data
                Commit: function() {
                    return this._makeRequest('commit', {})
                        .then(response => response.success ? "true" : "false");
                },

                // Error handling
                GetLastError: function() {
                    return 0;
                },

                GetErrorString: function(errorCode) {
                    return "No error";
                },

                GetDiagnostic: function(errorCode) {
                    return "";
                },

                // Internal request handler
                _makeRequest: function(action, data) {
                    const scoId = getCurrentScoId();
                    if (!scoId) return Promise.reject('No SCO ID available');

                    const routes = {
                        initialize: "{{ route('scorm.tracking.initialize', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        terminate: "{{ route('scorm.tracking.terminate', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        getvalue: "{{ route('scorm.tracking.getvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        setvalue: "{{ route('scorm.tracking.setvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}",
                        commit: "{{ route('scorm.tracking.commit', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}"
                    };

                    const url = routes[action].replace('--SCO_ID--', scoId);
                    const options = {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    };

                    if (action === 'getvalue') {
                        options.method = 'GET';
                    } else {
                        options.method = 'POST';
                        options.body = JSON.stringify(data);
                    }

                    return fetch(url, options)
                        .then(response => response.ok ? response.json() : Promise.reject('Network error'))
                        .catch(error => {
                            console.warn('SCORM 2004 API Error:', error);
                            throw error;
                        });
                }
            };

            // Quiz Handling for SCORM 2004
            window.RecordQuestion = async function(id, text, type, learnerResponse, correctAnswer, wasCorrect, objectiveId) {
                const scoId = getCurrentScoId();
                if (!scoId) return;
                await delay2004(100);

                fetch("{{ route('scorm.tracking.interaction', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        interaction_id: id,
                        type: type,
                        description: text,
                        learner_response: learnerResponse,
                        correct_response: correctAnswer,
                        result: wasCorrect ? 'correct' : 'incorrect',
                        weighting: 1.0,
                        latency: 0,
                        objective_id: objectiveId
                    })
                }).catch(console.error);
            };

            window.RecordTest = async function(score) {
                const scoId = getCurrentScoId();
                if (!scoId) return;

                const scaledScore = score / 100; // Convert to scaled score (-1 to 1)
                const completion_status = 'completed';
                const success_status = score >= 33 ? 'passed' : 'failed';

                // Update SCORM 2004 data model
                await window.API_1484_11.SetValue('cmi.score.scaled', scaledScore.toString());
                await delay2004(50);
                await window.API_1484_11.SetValue('cmi.score.raw', score.toString());
                await delay2004(50);
                await window.API_1484_11.SetValue('cmi.success_status', success_status);
                await delay2004(50);
                await window.API_1484_11.SetValue('cmi.completion_status', completion_status);
                await delay2004(50);
                await window.API_1484_11.Commit();

                console.log(`SCORM 2004 Test Recorded: score=${score}, scaled=${scaledScore}, status=${success_status}`);
            };
        </script>
    @endif

    <script>
        // Navigation and SCO management
        document.addEventListener('DOMContentLoaded', function() {
            const frame = document.getElementById('scormPlayerFrame');
            const routePattern = document.getElementById('route-patterns').dataset.contentRoute;
            const welcomeContent = document.getElementById('welcome-content');

            const scoEls = Array.from(document.querySelectorAll('[data-sco-id][data-launch]'));
            const scos = scoEls.map(el => ({
                id: el.dataset.scoId,
                el: el,
                launch: el.dataset.launch,
                title: el.querySelector('.sco-title')?.textContent?.trim() || ''
            }));

            let currentIndex = -1;

            function buildScormContentUrl(path) {
                if (!path) return '';
                const [pathname, query = ''] = path.split('?');
                const safePath = pathname.split('/').map(encodeURIComponent).join('/');
                return routePattern.replace('--PATH--', query ? safePath + '?' + query : safePath);
            }

            function loadSco(path, scoId = null) {
                if (!path) return;
                welcomeContent.style.display = 'none';
                frame.style.display = 'block';
                frame.src = buildScormContentUrl(path);
                frame.onload = function() {
                    console.log("SCO loaded:", scoId);
                    markCurrentSCOAsCompleted();
                    // setTimeout(() => {
                    //     updateSCOBadges();
                    // }, 100);
                };
            }

            // Initialize first SCO
            // const initialPath = "{{ $package->entry_point }}";
            // if (initialPath) loadSco(initialPath);

            // SCO navigation
            scos.forEach(function(item, i) {
                item.el.addEventListener('click', function(e) {
                    e.stopPropagation();

                    // Update UI
                    scos.forEach(s => s.el.classList.remove('sco-active', 'bg-blue-100', 'text-blue-700'));
                    item.el.classList.add('sco-active', 'bg-blue-100', 'text-blue-700');
                    document.getElementById('current-sco-title').textContent = item.title;
                    currentIndex = i;
                    updateNavButtons();

                    // Load SCO
                    loadSco(item.launch, item.id);
                });
            });

            function updateNavButtons() {
                const prevBtn = document.getElementById('prev-btn');
                const nextBtn = document.getElementById('next-btn');
                prevBtn.disabled = currentIndex <= 0;
                nextBtn.disabled = currentIndex >= scos.length - 1;

                [prevBtn, nextBtn].forEach(btn => {
                    if (btn.disabled) {
                        btn.classList.add('cursor-not-allowed', 'bg-gray-200', 'text-gray-700');
                        btn.classList.remove('cursor-pointer', 'bg-blue-600', 'text-white');
                    } else {
                        btn.classList.remove('cursor-not-allowed', 'bg-gray-200', 'text-gray-700');
                        btn.classList.add('cursor-pointer', 'bg-blue-600', 'text-white');
                    }
                });

            }

            document.getElementById('prev-btn').addEventListener('click', function() {
                if (currentIndex > 0) {
                    scos[currentIndex - 1].el.click();
                }
            });

            document.getElementById('next-btn').addEventListener('click', function() {
                if (currentIndex < scos.length - 1) {
                    // Update badges before navigating
                    scos[currentIndex + 1].el.click();
                }
            });

            window.getCurrentScoId = function() {
                const currentSco = document.querySelector('[data-sco-id].sco-active');
                return currentSco ? currentSco.dataset.scoId : (scos[0] ? scos[0].id : null);
            };

            // Function to mark current SCO as completed
            async function markCurrentSCOAsCompleted() {
                const scoId = getCurrentScoId();
                if (!scoId) return;

                try {
                    let successStatus = 'completed';
                    // For SCORM 1.2
                    if (window.API && window.API.LMSSetValue) {
                        await window.API.LMSSetValue('cmi.core.lesson_status', successStatus);
                        await window.API.LMSCommit();
                    }
                    // For SCORM 2004
                    else if (window.API_1484_11 && window.API_1484_11.SetValue) {
                        await window.API_1484_11.SetValue('cmi.completion_status', successStatus);
                        await window.API_1484_11.Commit();
                    }
                    updateSCOBadge(scoId, null, null, successStatus);
                    console.log('Marked SCO as completed:', scoId);
                } catch (error) {
                    console.warn('Failed to mark SCO as completed:', error);
                }
            }

            updateSCOBadges();

            // Auto-initialize first SCO
            // if (scos.length > 0 && currentIndex === -1) {
            //     setTimeout(() => scos[0].el.click(), 500);
            // }
        });
    </script>
    <script>
        // Simple badge update based on success_status
        async function updateSCOBadges() {
            try {
                const response = await fetch("{{ route('scorm.tracking.get-progress', ['package' => $package->id]) }}");
                const progressData = await response.json();

                progressData.forEach(scoProgress => {
                    updateSCOBadge(scoProgress.sco_id, scoProgress.success_status, scoProgress.score_percentage, scoProgress.completion_status);
                });
            } catch (error) {
                console.error('Failed to update badges:', error);
            }
        }

        // Update badge for a specific SCO
        function updateSCOBadge(scoId, successStatus, scorePercentage = null, completionStatus) {
            const badgeContainer = document.querySelector(`.badge-container[data-sco-id="${scoId}"]`);
            if (!badgeContainer) return;

            // Clear existing badge
            badgeContainer.innerHTML = '';

            const scoElement = badgeContainer.closest('.sco-item');
            if (!scoElement) return;

            // Remove existing status classes
            // scoElement.classList.remove(
            //     'border-l-4', 'border-l-green-500', 'border-l-blue-500',
            //     'border-l-red-500', 'border-l-yellow-500', 'border-l-gray-500',
            //     'border-l-purple-500'
            // );

            let badgeText = '';
            let badgeColor = '';
            let borderColor = '';
            let icon = '📚';

            // Handle completion status (for borders)
            if (completionStatus === 'completed' || completionStatus === 'completed' || completionStatus === 'unknown') {
                // borderColor = 'border-l-4 border-l-green-500';
                icon = '✅';
            } else {
                // borderColor = 'border-l-4 border-l-yellow-500';
                icon = '⏳';
            }

            // Handle success status (for badges) - only if successStatus exists
            if (successStatus) {
                switch (successStatus) {
                    case 'passed':
                        badgeText = 'Passed';
                        badgeColor = 'bg-blue-100 text-blue-800 border border-blue-300';
                        icon = '✅';
                        if (scorePercentage !== null) {
                            badgeText += ` (${Math.round(scorePercentage)}%)`;
                        }
                        break;

                    case 'failed':
                        badgeText = 'Failed';
                        badgeColor = 'bg-red-100 text-red-800 border border-red-300';
                        icon = '❌';
                        if (scorePercentage !== null) {
                            badgeText += ` (${Math.round(scorePercentage)}%)`;
                        }
                        break;

                    case 'completed':
                        // If it's just completed (no pass/fail), we only show border, no badge
                        badgeText = '';
                        break;

                    case 'unknown':
                        // Unknown status might just get border, no badge
                        badgeText = '';
                        break;

                    default:
                        badgeText = '';
                        break;
                }
            } else {
                // No success status, only completion status
                badgeText = '';
            }

            // Update icon
            const iconElement = scoElement.querySelector('.mr-2');
            if (iconElement) {
                iconElement.textContent = icon;
            }

            // Add border color (for completion status)
            if (borderColor) {
                scoElement.classList.add(...borderColor.split(' '));
            }
            console.log(completionStatus, successStatus, badgeText, borderColor);
            // Create badge (only for success status like passed/failed)
            if (badgeText) {
                const badge = document.createElement('span');
                badge.className = `text-xs font-mediumpx-2 px-2 py-1 rounded-full font-medium ${badgeColor}`;
                badge.textContent = badgeText;
                badgeContainer.appendChild(badge);
            }
        }
    </script>
@endsection
