window.API_1484_11 = {
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

    Initialize: function () {
        this._initialized = true;
        return "true";
    },

    Terminate: function () {
        this.Commit();
        this._initialized = false;
        return "true";
    },

    GetValue: function (key) {
        return this._data[key] || "";
    },

    SetValue: function (key, value) {
        this._data[key] = value;
        return "true";
    },

    Commit: function () {
        if (!this._scoId) {
            console.error("SCO ID not set. Call API_1484_11.init(scoId) first.");
            return "false";
        }

        // Append SCO ID to all keys for backend
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
        }).catch(err => console.error("SCORM 2004 commit failed:", err));

        return "true";
    },

    GetLastError: function () { return "0"; },
    GetErrorString: function () { return ""; },
    GetDiagnostic: function () { return ""; },
};
