/*!
 * swfupload.queue.js - File queue uploading support derived from SWFUpload
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */
var SWFUpload;
if (typeof(SWFUpload) === "function") {
	SWFUpload.queue = {};

	SWFUpload.prototype.initSettings = (function (s) {
		return function () {
			if (typeof(s) === "function") s.call(this);

			this.qsettings = {};

			this.qsettings.cancelled = false;
			this.qsettings.uploaded = 0;

			this.qsettings.user_upload_complete_handler = this.settings.upload_complete_handler;
			this.qsettings.user_upload_start_handler = this.settings.upload_start_handler;
			this.settings.upload_complete_handler = SWFUpload.queue.uploadCompleteHandler;
			this.settings.upload_start_handler = SWFUpload.queue.uploadStartHandler;

			this.settings.queue_complete_handler = this.settings.queue_complete_handler || null;
		};
	})(SWFUpload.prototype.initSettings);

	SWFUpload.prototype.startUpload = function (fileID) {
		this.qsettings.cancelled = false;
		this.callFlash("StartUpload", [fileID]);
	};

	SWFUpload.prototype.cancelQueue = function () {
		this.qsettings.cancelled = true;
		this.stopUpload();

		var stats = this.getStats();
		while (stats.files_queued > 0) {
			this.cancelUpload();
			stats = this.getStats();
		}
	};

	SWFUpload.queue.uploadStartHandler = function (file) {
		var returnValue;
		if (typeof(this.qsettings.user_upload_start_handler) === "function")
			returnValue = this.qsettings.user_upload_start_handler.call(this, file);

		// To prevent upload a real "FALSE" value must be returned, otherwise default to a real "TRUE" value.
		returnValue = (returnValue === false) ? false : true;

		this.qsettings.cancelled = !returnValue;

		return returnValue;
	};

	SWFUpload.queue.uploadCompleteHandler = function (file) {
		var continueUpload, stats, user_upload_complete_handler = this.qsettings.user_upload_complete_handler;

		if (file.filestatus === SWFUpload.FILE_STATUS.COMPLETE)
			this.qsettings.uploaded++;

		if (typeof(user_upload_complete_handler) === "function") {
			continueUpload = (user_upload_complete_handler.call(this, file) === false) ? false : true;
		} else if (file.filestatus === SWFUpload.FILE_STATUS.QUEUED) {
			// If the file was stopped and re-queued don't restart the upload
			continueUpload = false;
		} else {
			continueUpload = true;
		}

		if (continueUpload) {
			stats = this.getStats();
			if (stats.files_queued > 0 && this.qsettings.cancelled === false) {
				this.startUpload();
			} else if (this.qsettings.cancelled === false) {
				this.queueEvent("queue_complete_handler", [this.qsettings.uploaded]);
				this.qsettings.uploaded = 0;
			} else {
				this.qsettings.cancelled = false;
				this.qsettings.uploaded = 0;
			}
		}
	};
}