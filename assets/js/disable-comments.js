"use strict";

wp.domReady(function () {
	if (wp.blocks) {
		wp.blocks.unregisterBlockType("core/latest-comments");
	}
});
