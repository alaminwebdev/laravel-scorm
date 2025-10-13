window.API = {
    _initialized: false,
    _data: {},
    _scoId: null, // Current SCO ID

    /**
     * Initialize API with SCO ID
     * @param {Number} scoId 
     */
    init: function (scoId) {
        if (!scoId) console.error("SCO ID is required!");
        this._scoId = scoId;
        this._initialized = true;
    },

    LMSInitialize: function () {
        this._initialized = true;
        return "true";
    },

    LMSFinish: function () {
        return this.LMSCommit();
    },

    LMSGetValue: function (key) {
        return this._data[key] || "";
    },

    LMSSetValue: function (key, value) {
        this._data[key] = value;
        return "true";
    },

    LMSCommit: function () {
        if (!this._scoId) {
            console.error("SCO ID not set. Call API.init(scoId) first.");
            return "false";
        }

        // Prepare payload with SCO ID appended to keys
        const payload = {};
        for (let key in this._data) {
            payload[key + "_" + this._scoId] = this._data[key];
        }

        fetch('/scorm/api/commit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(payload)
        }).catch(err => console.error("SCORM commit failed:", err));

        return "true";
    },

    LMSGetLastError: function () { return "0"; },
    LMSGetErrorString: function () { return ""; },
    LMSGetDiagnostic: function () { return ""; },
};
