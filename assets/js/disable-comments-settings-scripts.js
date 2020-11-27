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

	/**
	 * Quick Setup Settings Scripts
	 */
	if (jQuery("#disablecommentssetupwrap").length > 0) {
		// disable nav click
		jQuery("ul.dc-quick__setup__nav li a").on("click", function (e) {
			e.stopPropagation();
			e.preventDefault();
		});
		var btnPrevious = "#dcQuickPreviousBtn";
		var btnNext = "#dcQuickNextBtn";
		var btnSkip = "#dcQuickSkipBtn";
		var finishStepFlug = false;
		var tabPosition = localStorage.getItem("dcqTabPostion");
		if (!tabPosition || tabPosition == null || isNaN(tabPosition)) {
			localStorage.setItem("dcqTabPostion", 1);
			tabPosition = 1;
		}
		changeTab(tabPosition); // window load

		// click button
		jQuery(btnPrevious).on("click", function (e) {
			e.preventDefault();
			finishStepFlug = false;
			updateTabPosition(localStorage.getItem("dcqTabPostion"), true);
		});
		jQuery(btnNext).on("click", function (e) {
			e.preventDefault();
			updateTabPosition(localStorage.getItem("dcqTabPostion"));
			save_settings(parseInt(localStorage.getItem("dcqTabPostion") - 1));
		});
		jQuery(btnSkip).on("click", function (e) {
			e.preventDefault();
			finishStepFlug = false;
			updateTabPosition(localStorage.getItem("dcqTabPostion"));
		});

		function updateTabPosition(tabPosition, isDecrement = false) {
			if (isDecrement === true) {
				if (1 < tabPosition) {
					--tabPosition;
				}
			} else {
				if (3 > tabPosition) {
					++tabPosition;
				}
			}
			localStorage.setItem("dcqTabPostion", tabPosition);
			changeTab(tabPosition);
		}
		function changeTab(nthChildNumber) {
			for (var i = 1; i <= 3; i++) {
				if (i <= nthChildNumber) {
					jQuery(
						"ul.dc-quick__setup__nav li:nth-child(" + i + ")"
					).addClass("active");
				} else {
					jQuery(
						"ul.dc-quick__setup__nav li:nth-child(" + i + ")"
					).removeClass("active");
				}
				// active tab
				if (nthChildNumber == i) {
					jQuery("#dcqTabBody_" + nthChildNumber).addClass("active");
				} else {
					jQuery("#dcqTabBody_" + i).removeClass("active");
				}
				// active tab control
				if (nthChildNumber == 1) {
					jQuery(btnPrevious).css("visibility", "hidden");
					jQuery(btnSkip).css("visibility", "hidden");
				} else {
					jQuery(btnPrevious).css("visibility", "visible");
					jQuery(btnSkip).css("visibility", "visible");
				}
				if (nthChildNumber == 3) {
					jQuery(btnSkip).css("visibility", "hidden");
					jQuery(btnNext).text("Finish");
				} else {
					jQuery(btnNext).text("Next");
				}
			}
		}
		function save_settings(tabPosition) {
			// get all form data

			if (finishStepFlug) {
				window.location = disableCommentsObj.settings_URI;
			} else {
				if (
					tabPosition == 1 &&
					jQuery("[name=dc_is_optin]").is(":checked")
				) {
					var data = {
						action: "optin_wizard_action_disable_comments",
					};
					jQuery
						.post(ajaxurl, data, function (response) {
							console.log("Optin Success");
						})
						.fail(function () {
							console.error("Optin Failed");
						});
				} else if (tabPosition == 2) {
					finishStepFlug = true;
					var data = {
						action: disableCommentsObj.save_action,
						nonce: disableCommentsObj._nonce,
						data: jQuery(
							"#disableCommentSaveSettings"
						).serializeArray(),
					};
					jQuery
						.post(ajaxurl, data, function (response) {})
						.fail(function () {
							console.error("Settings failed to saved.");
						});
				}
			}
		}

		// what we collect toggle
		jQuery("#whatWeCollect").on("click", function (e) {
			e.preventDefault();
			jQuery("#whatWeCollectMessage").slideToggle("fast");
		});
	}
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
