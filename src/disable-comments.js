wp.domReady(() => {
	if(wp.blocks) {
		wp.blocks.unregisterBlockType('core/latest-comments');
	}
});
