"use strict";

wp.domReady(function () {
  var blockType = 'core/latest-comments';
  if (wp.blocks && wp.data && wp.data.select('core/blocks').getBlockType(blockType)) {
    wp.blocks.unregisterBlockType(blockType);
  }
});
//# sourceMappingURL=disable-comments.js.map
