jQuery(document).ready(function ($) {
	var saveBtn   = jQuery("#disableCommentSaveSettings button.button.button__success");
	var deleteBtn = jQuery("#deleteCommentSettings button.button.button__delete");
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
	function enable_site_wise_uihelper() {
		var indiv_bits = jQuery(
			".disabled__sites .remove__checklist__item"
		);
		if (jQuery("#sitewide_settings").is(":checked")) {
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

	jQuery("#sitewide_settings").on('change', function () {
		enable_site_wise_uihelper();
	});
	enable_site_wise_uihelper();

	function disable_comments_uihelper() {
		var indiv_bits = jQuery(
			"#disable__post__types .remove__checklist__item, #extratypes"
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
			"#delete__post__types .delete__checklist__item, #extradeletetypes"
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

		jQuery.ajax({
			url: ajaxurl,
			type: "post",
			data: data,
			beforeSend: function () {
				saveBtn.html(
					'<svg id="eael-spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><circle cx="24" cy="4" r="4" fill="#fff"/><circle cx="12.19" cy="7.86" r="3.7" fill="#fffbf2"/><circle cx="5.02" cy="17.68" r="3.4" fill="#fef7e4"/><circle cx="5.02" cy="30.32" r="3.1" fill="#fef3d7"/><circle cx="12.19" cy="40.14" r="2.8" fill="#feefc9"/><circle cx="24" cy="44" r="2.5" fill="#feebbc"/><circle cx="35.81" cy="40.14" r="2.2" fill="#fde7af"/><circle cx="42.98" cy="30.32" r="1.9" fill="#fde3a1"/><circle cx="42.98" cy="17.68" r="1.6" fill="#fddf94"/><circle cx="35.81" cy="7.86" r="1.3" fill="#fcdb86"/></svg><span>Saving Settings..</span>'
				);
			},
			success: function (response) {
				if (response.success) {
					saveBtn.html("Save Settings");
					Swal.fire({
						icon: "success",
						title: response.data.message,
						timer: 3000,
						showConfirmButton: false,
					});
					saveBtn.removeClass('form-dirty');
				}
			},
			error: function () {
				saveBtn.html("Save Settings");
				Swal.fire({
					type: "error",
					title: "Oops...",
					text: "Something went wrong!",
				});
			},
		});
	});
	jQuery("#deleteCommentSettings").on("submit", function (e) {
		e.preventDefault();
		var $form = jQuery(this);
		Swal.fire({
			icon: "error",
			title: "Are you sure?",
			text: "You won't be able to revert this!",
			showConfirmButton: true,
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete It',
            cancelButtonText: 'No, Cancel',
			customClass: {
				confirmButton: 'confirmButton',
				cancelButton: 'cancelButton'
			  },
            reverseButtons: true,
		}).then(function(result){
            if (result.isConfirmed) {
				// Swal.fire({
				// 	icon: "info",
				// 	title: "Deleting comments...",
				// 	text: "Please wait.",
				// 	showConfirmButton: false,
				// });
				var data = {
					action: disableCommentsObj.delete_action,
					nonce: disableCommentsObj._nonce,
					data: $form.serializeArray(),
				};
				deleteBtn.html(
					'<svg id="eael-spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><circle cx="24" cy="4" r="4" fill="#fff"/><circle cx="12.19" cy="7.86" r="3.7" fill="#fffbf2"/><circle cx="5.02" cy="17.68" r="3.4" fill="#fef7e4"/><circle cx="5.02" cy="30.32" r="3.1" fill="#fef3d7"/><circle cx="12.19" cy="40.14" r="2.8" fill="#feefc9"/><circle cx="24" cy="44" r="2.5" fill="#feebbc"/><circle cx="35.81" cy="40.14" r="2.2" fill="#fde7af"/><circle cx="42.98" cy="30.32" r="1.9" fill="#fde3a1"/><circle cx="42.98" cy="17.68" r="1.6" fill="#fddf94"/><circle cx="35.81" cy="7.86" r="1.3" fill="#fcdb86"/></svg><span>Deleting Comments..</span>'
				);
				jQuery.post(ajaxurl, data, function (response) {
					deleteBtn.html("Delete Comments");
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
			}
		});
	});

	jQuery("#disableCommentSaveSettings").on('change keydown', 'input', function (e) {
		// jQuery(this).off(e);
		saveBtn.addClass('form-dirty');
	});
	jQuery("#deleteCommentSettings .check-all, #disableCommentSaveSettings .check-all").on('change', function(){
		var checked      = jQuery(this).is(':checked');
		var sites_option = jQuery(this).closest('.sites_option')
		var site_option  = sites_option.find('.site_option')
		site_option.prop('checked', checked);
	});

	var countSelected = function(sites_option){
		var site_option  = sites_option.find('.site_option')
		var totalChecked = 0;
		site_option.each(function(){
			if(jQuery(this).is(':checked')){
				totalChecked++;
			}
		});

		if(totalChecked){
			sites_option.find('.check-all').addClass('semi-checked');
		}
		sites_option.find('.check-all').prop('checked', totalChecked == site_option.length);
		sites_option.find('.check-all+label small').text(`(${totalChecked} selected)`)
	}

	jQuery("#deleteCommentSettings .sites_option, #disableCommentSaveSettings .sites_option").on('change', function(){
		var sites_option = jQuery(this).closest('.sites_option')
		countSelected(sites_option);
	});
	countSelected(jQuery("#deleteCommentSettings .sites_option"));
	countSelected(jQuery("#disableCommentSaveSettings .sites_option"));
});
