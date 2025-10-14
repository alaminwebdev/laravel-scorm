window.API_1484_11 = {
    _initialized: false,
    _data: {},
    _scoId: null,
    _sessionStart: null,

    /**
     * Initialize API with SCO ID
     */
    init: function (scoId) {
        if (!scoId) {
            console.error("SCO ID is required for SCORM 2004 API!");
            return;
        }
        this._scoId = scoId;
        this._sessionStart = new Date();
        this._initialized = true;
        this.loadProgress();
        console.log("SCORM 2004 API initialized for SCO:", scoId);
    },

    Initialize: function () {
        if (!this._scoId) {
            console.error("Call API_1484_11.init(scoId) first!");
            return "false";
        }
        this._initialized = true;
        return "true";
    },

    Terminate: function () {
        if (!this._initialized) return "false";

        // Calculate and save session time
        const sessionTime = this.calculateSessionTime();
        this._data['cmi.session_time'] = sessionTime;

        this.saveProgress();
        this._initialized = false;
        return "true";
    },

    GetValue: function (key) {
        if (!this._initialized) return "";

        const value = this._data[key] || "";
        console.log("SCORM 2004 GetValue:", key, "=", value);
        return value;
    },

    SetValue: function (key, value) {
        if (!this._initialized) return "false";

        console.log("SCORM 2004 SetValue:", key, "=", value);
        this._data[key] = value;

        // Auto-commit important data changes
        const importantKeys = [
            'cmi.completion_status',
            'cmi.success_status',
            'cmi.score.scaled',
            'cmi.score.raw',
            'cmi.suspend_data',
            'cmi.location',
            'cmi.progress_measure'
        ];

        if (importantKeys.includes(key)) {
            setTimeout(() => this.Commit(), 100);
        }

        return "true";
    },

    Commit: function () {
        if (!this._initialized || !this._scoId) return "false";

        this.saveProgress();
        return "true";
    },

    GetLastError: function () {
        return "0";
    },

    GetErrorString: function (errorCode) {
        return "";
    },

    GetDiagnostic: function (errorCode) {
        return "";
    },

    /**
     * Save progress to backend
     */
    saveProgress: function () {
        if (!this._scoId) return;

        const sessionTime = this.calculateSessionTime();

        const payload = {
            sco_id: this._scoId,
            data: this._data,
            session_time: sessionTime,
            last_accessed_at: new Date().toISOString()
        };

        // Extract SCORM 2004 specific data
        const completionStatus = this._data['cmi.completion_status'];
        const successStatus = this._data['cmi.success_status'];
        const scoreScaled = this._data['cmi.score.scaled'];
        const scoreRaw = this._data['cmi.score.raw'];
        const suspendData = this._data['cmi.suspend_data'];

        if (completionStatus) {
            payload.completion_status = completionStatus;
            payload.status = this.mapCompletionStatus(completionStatus);
        }

        if (successStatus) {
            payload.success_status = successStatus;
            if (!payload.status || payload.status === 'not_attempted') {
                payload.status = successStatus === 'passed' ? 'passed' : 'failed';
            }
        }

        // Use scaled score if available, otherwise raw score
        if (scoreScaled !== undefined) {
            payload.score = parseFloat(scoreScaled) * 100; // Convert to percentage
        } else if (scoreRaw !== undefined) {
            payload.score = parseFloat(scoreRaw);
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
            .then(data => console.log('SCORM 2004 progress saved'))
            .catch(error => console.error('SCORM 2004 save failed:', error));
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
                    console.log('SCORM 2004 progress loaded:', data);
                }
            })
            .catch(error => console.error('SCORM 2004 load failed:', error));
    },

    /**
     * Calculate session time in SCORM format (HH:MM:SS.SS)
     */
    calculateSessionTime: function () {
        if (!this._sessionStart) return "00:00:00.00";

        const now = new Date();
        const diff = now - this._sessionStart;
        const totalSeconds = diff / 1000;

        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = Math.floor(totalSeconds % 60);
        const hundredths = Math.floor((totalSeconds % 1) * 100);

        return [
            hours.toString().padStart(2, '0'),
            minutes.toString().padStart(2, '0'),
            seconds.toString().padStart(2, '0') + '.' + hundredths.toString().padStart(2, '0')
        ].join(':');
    },

    /**
     * Map SCORM 2004 completion_status to our status enum
     */
    mapCompletionStatus: function (completionStatus) {
        const statusMap = {
            'completed': 'completed',
            'incomplete': 'incomplete',
            'not attempted': 'not_attempted',
            'unknown': 'not_attempted'
        };

        return statusMap[completionStatus.toLowerCase()] || 'not_attempted';
    }
};

// Auto-initialize if scoId is in global scope
if (typeof window.SCORM_SCO_ID !== 'undefined') {
    window.API_1484_11.init(window.SCORM_SCO_ID);
}