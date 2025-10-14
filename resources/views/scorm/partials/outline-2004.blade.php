<script>
    window.SCORM_API_FOR_IFRAME = (function() {
        const USER_ID = "{{ auth()->id() }}";
        const USER_NAME = "{{ auth()->user()->name ?? 'Student' }}";
        let scormData = {};
        let initialized = false;
        let sessionStart = new Date();

        const API_1484_11 = {
            Initialize() {
                initialized = true;
                scormData['cmi.learner_id'] = USER_ID;
                scormData['cmi.learner_name'] = USER_NAME;
                scormData['cmi.completion_status'] = scormData['cmi.completion_status'] || 'not attempted';
                return "true";
            },
            Terminate() {
                saveProgress();
                initialized = false;
                return "true";
            },
            GetValue(key) {
                return initialized ? scormData[key] || "" : "";
            },
            SetValue(key, val) {
                if (!initialized) return "false";
                scormData[key] = val;
                if (['cmi.completion_status', 'cmi.success_status', 'cmi.score.scaled'].includes(key)) {
                    setTimeout(() => API_1484_11.Commit(), 100);
                }
                return "true";
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

        async function saveProgress() {
            const sessionSeconds = Math.floor((new Date() - sessionStart) / 1000);
            const payload = {
                data: scormData,
                session_time: new Date(sessionSeconds * 1000).toISOString().substr(11, 8),
                last_accessed_at: new Date().toISOString()
            };
            try {
                await fetch('/scorm/api/progress', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(payload)
                });
            } catch (err) {
                console.warn('Parent: progress save failed', err);
            }
        }

        return {
            API_1484_11,
            findAPI(win) {
                if (!win) return null;
                if (win.API_1484_11) return win.API_1484_11;
                if (win.parent && win.parent !== win) return this.findAPI(win.parent);
                return null;
            }
        };
    })();
</script>
