jQuery(document).ready(function () {
	/**
	 * Settings Scripts
	 */
	// tabs
	function disbale_comments_tabs() {
		var hash = window.location.hash;
		var tabNavItem =
			"ul.disable__comment__nav li.disable__comment__nav__item";
		var tabBodyItem = ".disable__comment__tab .disable__comment__tab__item";
		jQuery(tabNavItem).on("click", "a", function (e) {
			e.preventDefault();
			jQuery(this)
				.addClass("active")
				.parent()
				.siblings()
				.children()
				.removeClass("active");
			var target = jQuery(this).attr("href");
			jQuery(target).addClass("show").siblings().removeClass("show");
		});
		if (hash === "#delete") {
			jQuery("#disableCommentsNav > a").removeClass("active");
			jQuery("#disableComments").removeClass("show");
			jQuery("#deleteCommentsNav > a").addClass("active");
			jQuery("#deleteComments").addClass("show");
		}
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

	jQuery("#remove_everywhere, #selected_types").on('change', function () {
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
	).on('change', function () {
		delete_comments_uihelper();
	});
	delete_comments_uihelper();

	/**
	 * Settings Ajax Request
	 */
	jQuery("#disableCommentSaveSettings").on("submit", function (e) {
		e.preventDefault();
		var data = {
			action: disableCommentsObj.save_action,
			nonce: disableCommentsObj._nonce,
			data: jQuery(this).serializeArray(),
		};
		jQuery.post(ajaxurl, data, function (response) {
			if (response.success) {
				Swal.fire({
					icon: "success",
					title: response.data.message,
					timer: 3000,
					showConfirmButton: false,
				});
			}
		});
	});
	jQuery("#deleteCommentSettings").on("submit", function (e) {
		e.preventDefault();
		Swal.fire({
			icon: "info",
			title: "Request Sending...",
			text: "Please wait.",
			showConfirmButton: false,
		});
		var data = {
			action: disableCommentsObj.delete_action,
			nonce: disableCommentsObj._nonce,
			data: jQuery(this).serializeArray(),
		};
		jQuery.post(ajaxurl, data, function (response) {
			if (response.success) {
				Swal.fire({
					icon: "success",
					title: "Deleted",
					html: response.data.message,
					timer: 3000,
					showConfirmButton: false,
				});
			} else {
				Swal.fire({
					icon: "error",
					title: "Oops...",
					html: response.data.message,
					showConfirmButton: true,
				});
			}
		});
	});
});
