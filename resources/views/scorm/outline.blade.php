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
            // Simple delay function to prevent too many concurrent requests
            let requestDelay = 0;
            const delayBetweenRequests = 100; // 100ms delay between requests

            function delayedFetch(url, options) {
                return new Promise((resolve, reject) => {
                    setTimeout(() => {
                        fetch(url, options)
                            .then(resolve)
                            .catch(reject);
                    }, requestDelay);

                    requestDelay += delayBetweenRequests;
                });
            }

            window.API = {
                LMSInitialize: (param) => delayedFetch("{{ route('scorm.tracking.initialize', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                LMSFinish: (param) => delayedFetch("{{ route('scorm.tracking.terminate', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                LMSGetValue: (element) => delayedFetch("{{ route('scorm.tracking.getvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()) + `?element=${encodeURIComponent(element)}`, {
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(data => data.value || "").catch(() => ""),

                LMSSetValue: (element, value) => delayedFetch("{{ route('scorm.tracking.setvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        element,
                        value
                    })
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                LMSCommit: (param) => delayedFetch("{{ route('scorm.tracking.commit', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                LMSGetLastError: () => 0,
                LMSGetErrorString: (errorCode) => "No error",
                LMSGetDiagnostic: (errorCode) => "",

                // Enhanced RecordQuestion with proper interaction tracking
                RecordQuestion: (id, text, type, learnerResponse, correctAnswer, wasCorrect, objectiveId) => {
                    const scoId = getCurrentScoId();
                    if (!scoId) return;

                    // Store interaction in database with delay
                    delayedFetch("{{ route('scorm.tracking.interaction', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            interaction_id: id,
                            type: type || 'choice',
                            description: text,
                            learner_response: Array.isArray(learnerResponse) ? learnerResponse.join(',') : learnerResponse,
                            correct_response: Array.isArray(correctAnswer) ? correctAnswer.join(',') : correctAnswer,
                            result: wasCorrect ? 'correct' : 'incorrect',
                            weighting: 1.0,
                            latency: 0
                        })
                    });

                    // Update SCORM data model
                    const interactionIndex = Math.floor(Math.random() * 1000); // Simple index generation

                    // Set interaction data with delays
                    const setValue = (element, value) => {
                        delayedFetch("{{ route('scorm.tracking.setvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                element,
                                value
                            })
                        });
                    };

                    // Set basic interaction data
                    setValue(`cmi.interactions.${interactionIndex}.id`, id);
                    setValue(`cmi.interactions.${interactionIndex}.type`, type || 'choice');
                    setValue(`cmi.interactions.${interactionIndex}.student_response`, learnerResponse);
                    setValue(`cmi.interactions.${interactionIndex}.result`, wasCorrect ? 'correct' : 'wrong');
                    setValue(`cmi.interactions.${interactionIndex}.time`, new Date().toISOString().substr(11, 8));

                    if (objectiveId) {
                        setValue(`cmi.interactions.${interactionIndex}.objectives.0.id`, objectiveId);
                    }

                    console.log(`Question recorded: ${id}, correct: ${wasCorrect}`);
                },

                // Enhanced RecordTest with comprehensive tracking
                RecordTest: (score) => {
                    const scoId = getCurrentScoId();
                    if (!scoId) return;

                    const lesson_status = score >= 70 ? 'passed' : 'failed';
                    const completion_status = score >= 70 ? 'completed' : 'incomplete';
                    const success_status = score >= 70 ? 'passed' : 'failed';

                    // Update score and status
                    const updates = [{
                            element: 'cmi.core.score.raw',
                            value: score
                        },
                        {
                            element: 'cmi.core.lesson_status',
                            value: lesson_status
                        },
                        {
                            element: 'cmi.completion_status',
                            value: completion_status
                        },
                        {
                            element: 'cmi.success_status',
                            value: success_status
                        },
                        {
                            element: 'cmi.score.raw',
                            value: score
                        }
                    ];

                    const updatePromises = updates.map(update =>
                        delayedFetch("{{ route('scorm.tracking.setvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(update)
                        })
                    );

                    // Execute all updates
                    Promise.all(updatePromises).then(() => {
                        // Commit changes
                        delayedFetch("{{ route('scorm.tracking.commit', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        // Update final status
                        delayedFetch("{{ route('scorm.tracking.terminate-save-status', ['package' => $package->id]) }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                lesson_status,
                                completion_status,
                                success_status,
                                score_raw: score
                            })
                        });
                    });

                    console.log(`Test Recorded: score=${score}, status=${lesson_status}`);
                }
            };

            window.RecordQuestion = window.API.RecordQuestion;
            window.RecordTest = window.API.RecordTest;
        </script>
    @else
        <script>
            // Simple delay function for SCORM 2004
            let requestDelay2004 = 0;
            const delayBetweenRequests2004 = 100;

            function delayedFetch2004(url, options) {
                return new Promise((resolve, reject) => {
                    setTimeout(() => {
                        fetch(url, options)
                            .then(resolve)
                            .catch(reject);
                    }, requestDelay2004);

                    requestDelay2004 += delayBetweenRequests2004;
                });
            }

            window.API_1484_11 = {
                Initialize: () => delayedFetch2004("{{ route('scorm.tracking.initialize', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                Terminate: () => delayedFetch2004("{{ route('scorm.tracking.terminate', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                GetValue: (element) => delayedFetch2004("{{ route('scorm.tracking.getvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()) + `?element=${encodeURIComponent(element)}`, {
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(data => data.value || "").catch(() => ""),

                SetValue: (element, value) => delayedFetch2004("{{ route('scorm.tracking.setvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        element,
                        value
                    })
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                Commit: () => delayedFetch2004("{{ route('scorm.tracking.commit', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', getCurrentScoId()), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(r => r.json()).then(() => "true").catch(() => "false"),

                GetLastError: () => 0,
                GetErrorString: () => "No error",
                GetDiagnostic: () => "",

                // Enhanced RecordQuestion for SCORM 2004
                RecordQuestion: (id, text, type, learnerResponse, correctAnswer, wasCorrect, objectiveId) => {
                    const scoId = getCurrentScoId();
                    if (!scoId) return;

                    // Store interaction in database with delay
                    delayedFetch2004("{{ route('scorm.tracking.interaction', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            interaction_id: id,
                            type: type || 'choice',
                            description: text,
                            learner_response: Array.isArray(learnerResponse) ? learnerResponse.join(',') : learnerResponse,
                            correct_response: Array.isArray(correctAnswer) ? correctAnswer.join(',') : correctAnswer,
                            result: wasCorrect ? 'correct' : 'incorrect',
                            weighting: 1.0,
                            latency: 0
                        })
                    });

                    console.log('RecordQuestion called for 2004 SCORM version', id, learnerResponse, wasCorrect);
                },

                // Enhanced RecordTest for SCORM 2004
                RecordTest: (score) => {
                    const scoId = getCurrentScoId();
                    if (!scoId) return;

                    const scaledScore = score / 100; // Convert to scaled score (-1 to 1)
                    const completion_status = 'completed';
                    const success_status = score >= 70 ? 'passed' : 'failed';

                    // Update SCORM 2004 data model
                    const updates = [{
                            element: 'cmi.score.scaled',
                            value: scaledScore
                        },
                        {
                            element: 'cmi.score.raw',
                            value: score
                        },
                        {
                            element: 'cmi.success_status',
                            value: success_status
                        },
                        {
                            element: 'cmi.completion_status',
                            value: completion_status
                        }
                    ];

                    updates.forEach(update => {
                        delayedFetch2004("{{ route('scorm.tracking.setvalue', ['package' => $package->id, 'sco' => '--SCO_ID--']) }}".replace('--SCO_ID--', scoId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(update)
                        });
                    });

                    console.log('RecordTest called for 2004 SCORM version', score, 'scaled:', scaledScore);
                }
            };
        </script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const frame = document.getElementById('scormPlayerFrame');
            const routePattern = document.getElementById('route-patterns').dataset.contentRoute;

            const scoEls = Array.from(document.querySelectorAll('[data-sco-id][data-launch]'));
            const scos = scoEls.map(el => ({
                id: el.dataset.scoId,
                el,
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
                frame.src = buildScormContentUrl(path);
                frame.onload = () => console.log("SCO loaded:", scoId);
            }

            const initialPath = "{{ $package->entry_point }}";
            if (initialPath) loadSco(initialPath);

            scos.forEach((item, i) => {
                item.el.addEventListener('click', e => {
                    e.stopPropagation();
                    scos.forEach(s => s.el.classList.remove('bg-blue-50', 'text-blue-700', 'border-blue-200', 'sco-active'));
                    item.el.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200', 'sco-active');
                    document.getElementById('current-sco-title').textContent = item.title;
                    currentIndex = i;
                    updateNavButtons();
                    loadSco(item.launch || initialPath, item.id);
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

            document.getElementById('prev-btn').addEventListener('click', () => {
                if (currentIndex > 0) scos[currentIndex - 1].el.click();
            });
            document.getElementById('next-btn').addEventListener('click', () => {
                if (currentIndex < scos.length - 1) scos[currentIndex + 1].el.click();
            });

            window.getCurrentScoId = function() {
                const currentSco = document.querySelector('[data-sco-id].sco-active');
                return currentSco?.dataset.scoId || scos[0]?.id || null;
            };

            // Auto-initialize the first SCO if none is active
            if (scos.length > 0 && currentIndex === -1) {
                setTimeout(() => {
                    scos[0].el.click();
                }, 1000);
            }
        });
    </script>
@endsection
