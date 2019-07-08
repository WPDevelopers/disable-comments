"use strict";

wp.domReady(function () {
  wp.blocks.getBlockTypes().forEach(function (block) {
    if (disable_comments.disabled_blocks.includes(block.name)) {
      wp.blocks.unregisterBlockType(block.name);
    }
  });
});
//# sourceMappingURL=disable-comments.js.map
