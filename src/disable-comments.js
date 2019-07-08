wp.domReady(() => {
	wp.blocks.getBlockTypes().forEach((block) => {
		if (disable_comments.disabled_blocks.includes(block.name)) {
			wp.blocks.unregisterBlockType(block.name);
		}
	});
});