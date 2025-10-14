window.API = {
    _initialized: false,
    _data: {},
    _scoId: null,
    _sessionStart: null,

    /**
     * Initialize API with SCO ID
     */
    init: function (scoId) {
        if (!scoId) {
            console.error("SCO ID is required for SCORM 1.2 API!");
            return;
        }
        this._scoId = scoId;
        this._sessionStart = new Date();
        this._initialized = true;
        this.loadProgress();
        console.log("SCORM 1.2 API initialized for SCO:", scoId);
    },

    LMSInitialize: function () {
        if (!this._scoId) {
            console.error("Call API.init(scoId) first!");
            return "false";
        }
        this._initialized = true;
        return "true";
    },

    LMSFinish: function () {
        if (!this._initialized) return "false";

        // Calculate session time
        const sessionTime = this.calculateSessionTime();
        this._data['cmi.core.session_time'] = sessionTime;

        this.saveProgress();
        this._initialized = false;
        return "true";
    },

    LMSGetValue: function (key) {
        if (!this._initialized) return "";

        const value = this._data[key] || "";
        console.log("SCORM 1.2 GetValue:", key, "=", value);
        return value;
    },

    LMSSetValue: function (key, value) {
        if (!this._initialized) return "false";

        console.log("SCORM 1.2 SetValue:", key, "=", value);
        this._data[key] = value;

        // Auto-commit important data changes
        const importantKeys = [
            'cmi.core.lesson_status',
            'cmi.core.score.raw',
            'cmi.core.exit',
            'cmi.suspend_data',
            'cmi.core.lesson_location'
        ];

        if (importantKeys.includes(key)) {
            setTimeout(() => this.LMSCommit(), 100);
        }

        return "true";
    },

    LMSCommit: function () {
        if (!this._initialized || !this._scoId) return "false";

        this.saveProgress();
        return "true";
    },

    LMSGetLastError: function () {
        return "0";
    },

    LMSGetErrorString: function (errorCode) {
        return "";
    },

    LMSGetDiagnostic: function (errorCode) {
        return "";
    },

    /**
     * Save progress to backend
     */
    saveProgress: function () {
        if (!this._scoId) return;

        const sessionTime = this.calculateSessionTime();

        // Map SCORM 1.2 data to our tracking structure
        const payload = {
            sco_id: this._scoId,
            data: this._data,
            session_time: sessionTime,
            last_accessed_at: new Date().toISOString()
        };

        // Extract status information
        const lessonStatus = this._data['cmi.core.lesson_status'];
        const scoreRaw = this._data['cmi.core.score.raw'];
        const suspendData = this._data['cmi.suspend_data'];

        if (lessonStatus) {
            payload.status = this.mapLessonStatus(lessonStatus);
            payload.completion_status = lessonStatus;
        }

        if (scoreRaw !== undefined) {
            payload.score = parseFloat(scoreRaw);

            // Determine success status based on score and passing score
            const masteryScore = this._data['cmi.student_data.mastery_score'];
            if (masteryScore && payload.score >= parseFloat(masteryScore)) {
                payload.success_status = 'passed';
            } else if (masteryScore) {
                payload.success_status = 'failed';
            }
        }

        if (suspendData) {
            payload.suspend_data = suspendData;
        }

        fetch('/scorm/api/progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => console.log('SCORM 1.2 progress saved'))
            .catch(error => console.error('SCORM 1.2 save failed:', error));
    },

    /**
     * Load progress from backend
     */
    loadProgress: function () {
        if (!this._scoId) return;

        fetch(`/scorm/api/progress/${this._scoId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data && data.data) {
                    this._data = { ...this._data, ...data.data };
                    console.log('SCORM 1.2 progress loaded:', data);
                }
            })
            .catch(error => console.error('SCORM 1.2 load failed:', error));
    },

    /**
     * Calculate session time in SCORM format (HH:MM:SS)
     */
    calculateSessionTime: function () {
        if (!this._sessionStart) return "00:00:00";

        const now = new Date();
        const diff = now - this._sessionStart;
        const seconds = Math.floor(diff / 1000);

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        return [
            hours.toString().padStart(2, '0'),
            minutes.toString().padStart(2, '0'),
            secs.toString().padStart(2, '0')
        ].join(':');
    },

    /**
     * Map SCORM 1.2 lesson_status to our status enum
     */
    mapLessonStatus: function (lessonStatus) {
        const statusMap = {
            'passed': 'passed',
            'completed': 'completed',
            'failed': 'failed',
            'incomplete': 'incomplete',
            'browsed': 'completed',
            'not attempted': 'not_attempted'
        };

        return statusMap[lessonStatus.toLowerCase()] || 'not_attempted';
    }
};

// Auto-initialize if scoId is in global scope
if (typeof window.SCORM_SCO_ID !== 'undefined') {
    window.API.init(window.SCORM_SCO_ID);
}