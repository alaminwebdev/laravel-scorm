<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $sco->title }}</title>
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            background: #f9f9f9;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
    </style>
</head>

<body>
    @if ($sco->launch)
        <iframe src="{{ route('scorm.content', ['package' => $package->id, 'path' => $sco->launch]) }}" id="scormFrame" allow="fullscreen" allowfullscreen>
        </iframe>
    @else
        <div style="text-align:center; padding: 2rem;">
            <h2>Content Not Available</h2>
            <p>This SCO doesn't have launchable content.</p>
        </div>
    @endif

    <script>
        /**
         * ======================================================
         *   SCORM API Wrapper (1.2 + 2004)
         * ======================================================
         */
        const SCO_ID = {{ $sco->id }};
        const SCORM_VERSION = "{{ $package->version }}";
        const USER_ID = "{{ auth()->id() }}";
        const USER_NAME = "{{ auth()->user()->name ?? 'Student' }}";
        let scormData = {};
        let isInitialized = false;
        let sessionStart = new Date();

        // =================== SCORM 1.2 ===================
        window.API = {
            LMSInitialize() {
                console.log("✅ SCORM 1.2 Initialized");
                isInitialized = true;
                scormData['cmi.core.student_id'] = USER_ID;
                scormData['cmi.core.student_name'] = USER_NAME;
                scormData['cmi.core.lesson_status'] = 'not attempted';
                scormData['cmi.core.score.raw'] = '0';
                loadProgress();
                return "true";
            },
            LMSFinish() {
                console.log("🟡 SCORM 1.2 Finish");
                saveProgress();
                isInitialized = false;
                return "true";
            },
            LMSSetValue(key, val) {
                if (!isInitialized) return "false";
                scormData[key] = val;
                console.log('SET:', key, '=', val);
                if (['cmi.core.lesson_status', 'cmi.core.score.raw'].includes(key)) this.LMSCommit();
                return "true";
            },
            LMSGetValue(key) {
                if (!isInitialized) return "";
                return scormData[key] || "";
            },
            LMSCommit() {
                saveProgress();
                return "true";
            },
            LMSGetLastError() {
                return "0";
            },
            LMSGetErrorString() {
                return "No error";
            },
            LMSGetDiagnostic() {
                return "";
            }
        };

        // =================== SCORM 2004 ===================
        window.API_1484_11 = {
            Initialize() {
                console.log("✅ SCORM 2004 Initialized");
                isInitialized = true;
                scormData['cmi.learner_id'] = USER_ID;
                scormData['cmi.learner_name'] = USER_NAME;
                scormData['cmi.completion_status'] = 'not attempted';
                scormData['cmi.score.scaled'] = '0';
                loadProgress();
                return "true";
            },
            Terminate() {
                console.log("🟡 SCORM 2004 Terminate");
                saveProgress();
                isInitialized = false;
                return "true";
            },
            SetValue(key, val) {
                if (!isInitialized) return "false";
                scormData[key] = val;
                console.log('SET:', key, '=', val);
                if (['cmi.completion_status', 'cmi.success_status', 'cmi.score.scaled'].includes(key)) this.Commit();
                return "true";
            },
            GetValue(key) {
                if (!isInitialized) return "";
                return scormData[key] || "";
            },
            Commit() {
                saveProgress();
                return "true";
            },
            GetLastError() {
                return "0";
            },
            GetErrorString() {
                return "No error";
            },
            GetDiagnostic() {
                return "";
            }
        };

        // =================== Common API Discovery ===================
        function findAPI(win) {
            if (win.API_1484_11) return win.API_1484_11;
            if (win.API) return win.API;
            if (win.parent && win.parent !== win) return findAPI(win.parent);
            return null;
        }

        // =================== Progress Handlers ===================
        async function saveProgress() {
            const now = new Date();
            const diff = Math.floor((now - sessionStart) / 1000);
            const sessionTime = new Date(diff * 1000).toISOString().substr(11, 8);

            const payload = {
                sco_id: SCO_ID,
                data: scormData,
                session_time: sessionTime,
                last_accessed_at: now.toISOString()
            };

            await fetch(`/scorm/api/progress`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            }).catch(err => console.warn('Save failed:', err));
        }

        async function loadProgress() {
            const res = await fetch(`/scorm/api/progress/${SCO_ID}`);
            if (res.ok) {
                const data = await res.json();
                if (data && data.data) scormData = {
                    ...scormData,
                    ...data.data
                };
            }
        }

        // =================== Iframe Hook ===================
        document.getElementById('scormFrame').addEventListener('load', function() {
            const iframeWindow = this.contentWindow;
            try {
                if (SCORM_VERSION === '2004') iframeWindow.API_1484_11 = window.API_1484_11;
                else iframeWindow.API = window.API;
                iframeWindow.findAPI = findAPI;
                iframeWindow.GetAPI = findAPI;
                console.log("🔗 API connected to iframe");
            } catch {
                console.warn("⚠️ Could not inject API (cross-origin?)");
            }
        });

        // =================== On Window Close ===================
        window.addEventListener('beforeunload', () => {
            if (isInitialized) {
                if (SCORM_VERSION === '2004') window.API_1484_11.Terminate();
                else window.API.LMSFinish();
            }
        });
    </script>
</body>

</html>
