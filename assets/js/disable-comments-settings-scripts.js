jQuery(document).ready(function () {
	if (jQuery("#disablecommentswrap").length) {
		// tabs
		function disbale_comments_tabs() {
			var tabNavItem =
				"ul.disable__comment__nav li.disable__comment__nav__item";
			var tabBodyItem =
				".disable__comment__tab .disable__comment__tab__item";
			jQuery(tabNavItem + "> a").on("click", function (e) {
				e.preventDefault();
				if (jQuery(tabNavItem + " a").hasClass("active")) {
					jQuery(tabNavItem + " a").removeClass("active");
				}
				jQuery(this).toggleClass("active");
				jQuery(tabBodyItem).toggleClass("show");
			});
		}
		disbale_comments_tabs();
		// UI Helper
		function disable_comments_uihelper() {
			var indiv_bits = jQuery(
				".remove__checklist .remove__checklist__item, #extratypes"
			);
			if (jQuery("#remove_everywhere").is(":checked")) {
				indiv_bits
					.css("opacity", ".3")
					.find(":input")
					.attr("disabled", true);
			} else {
				indiv_bits
					.css("opacity", "1")
					.find(":input")
					.attr("disabled", false);
			}
		}

		jQuery("#remove_everywhere, #selected_types").change(function () {
			jQuery("#message").slideUp();
			disable_comments_uihelper();
		});
		disable_comments_uihelper();

		function delete_comments_uihelper() {
			var toggle_pt_bits = jQuery(
				".delete__checklist .delete__checklist__item, #extradeletetypes"
			);
			var toggle_ct_bits = jQuery("#listofdeletecommenttypes");
			if (jQuery("#delete_everywhere").is(":checked")) {
				toggle_pt_bits
					.css("opacity", ".3")
					.find(":input")
					.attr("disabled", true);
				toggle_ct_bits
					.css("opacity", ".3")
					.find(":input")
					.attr("disabled", true);
			} else {
				if (jQuery("#selected_delete_types").is(":checked")) {
					toggle_pt_bits
						.css("opacity", "1")
						.find(":input")
						.attr("disabled", false);
					toggle_ct_bits
						.css("opacity", ".3")
						.find(":input")
						.attr("disabled", true);
				} else {
					toggle_ct_bits
						.css("opacity", "1")
						.find(":input")
						.attr("disabled", false);
					toggle_pt_bits
						.css("opacity", ".3")
						.find(":input")
						.attr("disabled", true);
				}
			}
		}

		jQuery(
			"#delete_everywhere, #selected_delete_types, #selected_delete_comment_types"
		).change(function () {
			delete_comments_uihelper();
		});
		delete_comments_uihelper();
	}
});
