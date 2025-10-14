<script>
window.SCORM_API_FOR_IFRAME = (function() {
    const USER_ID = "{{ auth()->id() }}";
    const USER_NAME = "{{ auth()->user()->name ?? 'Student' }}";
    let scormData = {};
    let initialized = false;
    let sessionStart = new Date();

    const API = {
        LMSInitialize() {
            initialized = true;
            scormData['cmi.core.student_id'] = USER_ID;
            scormData['cmi.core.student_name'] = USER_NAME;
            scormData['cmi.core.lesson_status'] = scormData['cmi.core.lesson_status'] || 'not attempted';
            return "true";
        },
        LMSFinish() {
            saveProgress();
            initialized = false;
            return "true";
        },
        LMSGetValue(key) {
            return initialized ? scormData[key] || "" : "";
        },
        LMSSetValue(key, val) {
            if (!initialized) return "false";
            scormData[key] = val;
            if (['cmi.core.lesson_status', 'cmi.core.score.raw', 'cmi.suspend_data'].includes(key)) {
                setTimeout(() => API.LMSCommit(), 100);
            }
            return "true";
        },
        LMSCommit() {
            saveProgress();
            return "true";
        },
        LMSGetLastError() { return "0"; },
        LMSGetErrorString() { return "No error"; },
        LMSGetDiagnostic() { return ""; }
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
        API,
        findAPI(win) {
            if (!win) return null;
            if (win.API) return win.API;
            if (win.parent && win.parent !== win) return this.findAPI(win.parent);
            return null;
        }
    };
})();
</script>
