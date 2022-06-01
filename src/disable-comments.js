wp.domReady(() => {
	const blockType = 'core/latest-comments';
	if(wp.blocks && wp.data && wp.data.select('core/blocks').getBlockType( blockType )) {
		wp.blocks.unregisterBlockType(blockType);
	}
});
