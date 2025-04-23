jQuery(document).ready(function ($) {
	var __        = wp.i18n.__;
	var _e        = wp.i18n._e;
	var sprintf   = wp.i18n.sprintf;
	var $form     = jQuery('#disableCommentSaveSettings');
	var saveBtn   = jQuery("#disableCommentSaveSettings button.button.button__success");
	var deleteBtn = jQuery("#deleteCommentSettings button.button.button__delete");
	var savedData;

	if(jQuery('.sites_list_wrapper').length){
		var addSite   = function($sites_list, site, type){
			var id        = "sites__option__" + type + "__" + site.site_id;
			var name      = "disabled_sites[site_" + site.site_id + "]";
			var hasOption = $sites_list.has('#' + id);
			if(hasOption.length){
				$sites_list.find('#' + id).parent().removeClass('hidden');
				return;
			}

			$sites_list.append( "\
				<div class='subsite__checklist__item checkbox-style'>\
					<input type='hidden' name='" + name + "' value='0' />\
					<input type='checkbox' id='" + id + "' class='site_option' name='" + name + "' value='1' " + site.is_checked + " />\
					<label for='" + id + "'>"
						+ "<i class='icon' tabindex='0'></i>"
						+ site.blogname +
					"</label>\
				</div>\
			");
		}
		var addSites  = function($sites_list, sub_sites, type){
			// $sites_list.html('');
			$sites_list.children().addClass('hidden');
			sub_sites.forEach(function(site) {
				addSite($sites_list, site, type);
			});
			if(sub_sites.length == 0){
				$sites_list.find('.nothing-found').removeClass('hidden');
			}
			enable_site_wise_uihelper();
		}

		jQuery(".sites_list_wrapper").each(function(){
			var $sites_list_wrapper = jQuery(this);
			var $subSiteSearch      = $sites_list_wrapper.find('.sub-site-search');
			var type                = $sites_list_wrapper.data('type');
			var $sites_list         = $sites_list_wrapper.find('.sites_list');
			var $pageSize           = $sites_list_wrapper.find('.page__size');
			var $pageSizeWrapper    = $sites_list_wrapper.find('.page__size__wrapper');
			var isPageLoaded        = {};
			var args                = {
				dataSource             : ajaxurl,
				locator                : 'data',
				pageSize               : $pageSize.val() || 50,
				showPageNumbers        : false,
				hideWhenLessThanOnePage: true,
				totalNumberLocator: function(response) {
					if(response.totalNumber <= 20){
						$pageSizeWrapper.hide();
					}
					else{
						$pageSizeWrapper.show();
					}
					return response.totalNumber;
				},
				ajax                   : function(){
					return {
						cache: true,
						data : {
							action: 'get_sub_sites',
							type  : type,
							search: $subSiteSearch.val(),
							nonce: disableCommentsObj._nonce,
						},
					};
				},
				callback       : function(data, pagination) {
					var pageNumber = pagination.pageNumber;
					addSites($sites_list, data, type);
					isPageLoaded[pageNumber] = data;
					countSelected($sites_list_wrapper);
				}
			};

			$sites_list_wrapper.find('.has-pagination').pagination(args);

			var timeoutID = null;
			$subSiteSearch.on('keyup keypress', function(event){
				if(event.type != 'keypress'){
					if(timeoutID){
						clearTimeout(timeoutID);
					}
					timeoutID = setTimeout(() => {
						$sites_list_wrapper.find('.has-pagination').pagination('go', 1);
					}, 1000);
				}
				var keyCode = event.keyCode || event.which;
				if (keyCode === 13) {
					event.preventDefault();
				  return false;
				}
			});

			$pageSize.on('change', function(){
				args.pageSize = jQuery(this).val();
				$sites_list_wrapper.find('.has-pagination').pagination(args);
			});
		});

		jQuery(".sites_list_wrapper .check-all").on('change', function(){
			var checked            = jQuery(this).is(':checked');
			var sites_list_wrapper = jQuery(this).closest('.sites_list_wrapper')
			var site_option        = sites_list_wrapper.find('.sites_list .subsite__checklist__item:not(.hidden)')
			site_option.find('.site_option').prop('checked', checked);
			// console.log(site_option);
		});

		var countSelected = function(sites_list_wrapper){
			var site_option  = sites_list_wrapper.find('.sites_list .subsite__checklist__item:not(.hidden)')
			var totalChecked = 0;
			site_option.find('.site_option').each(function(){
				if(jQuery(this).is(':checked')){
					totalChecked++;
				}
			});

			if(totalChecked){
				sites_list_wrapper.find('.check-all').addClass('semi-checked');
			}
			sites_list_wrapper.find('.check-all').prop('checked', totalChecked == site_option.length);
			sites_list_wrapper.find('.check-all+label .selected-count').text(`(${totalChecked} selected)`)
		}

		jQuery(".sites_list_wrapper").on('change', function(){
			var sites_list_wrapper = jQuery(this)
			countSelected(sites_list_wrapper);
		});

		countSelected(jQuery("#deleteCommentSettings .sites_list_wrapper"));
		countSelected(jQuery("#disableCommentSaveSettings .sites_list_wrapper"));
	}

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
		var pagination = jQuery("#disableCommentSaveSettings .sites_list_wrapper .has-pagination");
		var indiv_bits = jQuery(
			"#disableCommentSaveSettings .subsite__checklist__item, #disableCommentSaveSettings .sub__site_control"
		);
		if (jQuery("#sitewide_settings").is(":checked")) {
			pagination.length && pagination.addClass('disabled').pagination('disable', true);
			indiv_bits
				.css("opacity", ".3")
				.find(":input")
				.attr("disabled", true);
			indiv_bits
				.not('.sub__site_control')
				.find("label .icon")
				.attr("tabindex", -1);

		} else {
			pagination.length && pagination.removeClass('disabled').pagination('enable', true);
			indiv_bits
				.css("opacity", "1")
				.find(":input")
				.attr("disabled", false);
			indiv_bits
				.not('.sub__site_control')
				.find("label .icon")
				.attr("tabindex", '0');
		}
	}

	jQuery("#sitewide_settings").on('change', function () {
		enable_site_wise_uihelper();
	});
	enable_site_wise_uihelper();

	function disable_comments_uihelper() {
		var indiv_bits = jQuery(
			"#disable__post__types .remove__checklist__item, #disable__post__types .custom-types-input"
		);
		if (jQuery("#remove_everywhere").is(":checked")) {
			indiv_bits
				.css("opacity", ".3")
				.find(":input")
				.attr("disabled", true);
			jQuery("#disable__post__types .remove__checklist__item label .icon")
				.attr("tabindex", -1);
		} else {
			indiv_bits
				.css("opacity", "1")
				.find(":input")
				.attr("disabled", false);
			jQuery("#disable__post__types .remove__checklist__item label .icon")
				.attr("tabindex", '0');
		}
	}

	jQuery("#remove_everywhere, #selected_types").on('change', function () {
		jQuery("#message").slideUp();
		disable_comments_uihelper();
	});
	disable_comments_uihelper();

	function delete_comments_uihelper() {
		var toggle_pt_bits = jQuery(
			"#delete__post__types .delete__checklist__item, #delete__post__types .custom-types-input"
		);
		var toggle_ct_bits = jQuery("#listofdeletecommenttypes");
		if (jQuery("#delete_everywhere, #delete_spam").is(":checked")) {
			toggle_pt_bits
				.css("opacity", ".3")
				.find(":input")
				.attr("disabled", true);
			toggle_ct_bits
				.css("opacity", ".3")
				.find(":input")
				.attr("disabled", true);
			jQuery("#delete__post__types .checkbox-style label .icon, #listofdeletecommenttypes label .icon")
				.attr("tabindex", -1);
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
				jQuery("#delete__post__types .checkbox-style label .icon")
					.attr("tabindex", '0');
				jQuery("#listofdeletecommenttypes label .icon")
					.attr("tabindex", '-1');
			} else {
				toggle_ct_bits
					.css("opacity", "1")
					.find(":input")
					.attr("disabled", false);
				toggle_pt_bits
					.css("opacity", ".3")
					.find(":input")
					.attr("disabled", true);
				jQuery("#delete__post__types .checkbox-style label .icon")
					.attr("tabindex", -1);
				jQuery("#listofdeletecommenttypes label .icon")
					.attr("tabindex", '0');
			}
		}
	}

	jQuery(
		"#delete_everywhere, #delete_spam, #selected_delete_types, #selected_delete_comment_types"
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
			data: jQuery(this).serialize(),
		};

		jQuery.ajax({
			url: ajaxurl,
			type: "post",
			data: data,
			beforeSend: function () {
				var btnText = __("Saving Settings..", "disable-comments");
				saveBtn.html(
					'<svg id="eael-spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><circle cx="24" cy="4" r="4" fill="#fff"/><circle cx="12.19" cy="7.86" r="3.7" fill="#fffbf2"/><circle cx="5.02" cy="17.68" r="3.4" fill="#fef7e4"/><circle cx="5.02" cy="30.32" r="3.1" fill="#fef3d7"/><circle cx="12.19" cy="40.14" r="2.8" fill="#feefc9"/><circle cx="24" cy="44" r="2.5" fill="#feebbc"/><circle cx="35.81" cy="40.14" r="2.2" fill="#fde7af"/><circle cx="42.98" cy="30.32" r="1.9" fill="#fde3a1"/><circle cx="42.98" cy="17.68" r="1.6" fill="#fddf94"/><circle cx="35.81" cy="7.86" r="1.3" fill="#fcdb86"/></svg><span>' + btnText + '</span>'
				);
			},
			success: function (response) {
				if (response.success) {
					saveBtn.html(__("Save Settings", "disable-comments"));
					Swal.fire({
						icon: "success",
						title: response.data.message,
						timer: 3000,
						showConfirmButton: false,
					});
					saveBtn.removeClass('form-dirty').prop('disabled', true);
					savedData = $form.serialize();
				}
			},
			error: function () {
				saveBtn.html("Save Settings");
				Swal.fire({
					type: "error",
					title: __("Oops...", "disable-comments"),
					text: __("Something went wrong!", "disable-comments"),
				});
			},
		});
	});
	jQuery("#deleteCommentSettings").on("submit", function (e) {
		e.preventDefault();
		var $form = jQuery(this);
		Swal.fire({
			icon: "error",
			title: __("Are you sure?", "disable-comments"),
			text: __("You won't be able to reverse this without a database backup.", "disable-comments"),
			showConfirmButton: true,
            showCancelButton: true,
            confirmButtonText: __('Yes, Delete It', "disable-comments"),
            cancelButtonText: __('No, Cancel', "disable-comments"),
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
					data: $form.serialize(),
				};
				deleteBtn.html(
					'<svg id="eael-spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><circle cx="24" cy="4" r="4" fill="#fff"/><circle cx="12.19" cy="7.86" r="3.7" fill="#fffbf2"/><circle cx="5.02" cy="17.68" r="3.4" fill="#fef7e4"/><circle cx="5.02" cy="30.32" r="3.1" fill="#fef3d7"/><circle cx="12.19" cy="40.14" r="2.8" fill="#feefc9"/><circle cx="24" cy="44" r="2.5" fill="#feebbc"/><circle cx="35.81" cy="40.14" r="2.2" fill="#fde7af"/><circle cx="42.98" cy="30.32" r="1.9" fill="#fde3a1"/><circle cx="42.98" cy="17.68" r="1.6" fill="#fddf94"/><circle cx="35.81" cy="7.86" r="1.3" fill="#fcdb86"/></svg><span>' + __("Deleting Comments..", "disable-comments") + '</span>'
				);
				jQuery.post(ajaxurl, data, function (response) {
					deleteBtn.html(__("Delete Comments", "disable-comments"));
					if (response.success) {
						Swal.fire({
							icon: "success",
							title: __("Deleted", "disable-comments"),
							html: response.data.message,
							timer: 3000,
							showConfirmButton: false,
						});
					} else {
						Swal.fire({
							icon: "error",
							title: __("Oops...", "disable-comments"),
							html: response.data.message,
							showConfirmButton: true,
						});
					}
				});
			}
		});
	});

	jQuery("#disableCommentSaveSettings").on('change keydown', ':input', function (e) {
		if(!savedData){
			savedData = $form.serialize();
		}
		if(savedData == $form.serialize()){
			saveBtn.removeClass('form-dirty').prop('disabled', true);
		}
		else{
			saveBtn.addClass('form-dirty').prop('disabled', false);
		}

	});

	jQuery('#remove_everywhere').trigger('change');

	(function() {
		var excludeByRoleWrapper       = jQuery('#exclude_by_role_wrapper');
		if(!excludeByRoleWrapper.length) return;
		var excludeByRoleSelectWrapper = excludeByRoleWrapper.find('#exclude_by_role_select_wrapper');
		var excludeByRoleSelect        = excludeByRoleSelectWrapper.find('.dc-select2');
		var options                    = excludeByRoleSelect.data('options');
		var selectDescriptionWrapper   = excludeByRoleWrapper.find('#exclude_by_role_select_description_wrapper');
		var excludedRoles              = excludeByRoleWrapper.find('.excluded-roles');
		var includedRoles              = excludeByRoleWrapper.find('.included-roles');
		var selectOnChange             = function(){
			var selectedOptions = excludeByRoleSelect.select2('data');
			// console.log(selectedOptions);
			excludeByRoleSelectWrapper.show();
			if(selectedOptions.length){
				includedRoles.show();
				excludedRoles.show();
				var hasLoggedOutUsers = selectedOptions.find(function(val, index){
					return val.id == 'logged-out-users';
				});
				if(options.length == selectedOptions.length){
					excludedRoles.text(__("Comments are visible to everyone.", "disable-comments"));
					includedRoles.hide();
				}
				else if(hasLoggedOutUsers){
					if(selectedOptions.length == 1){
						excludedRoles.text(__("Users who are logged out will see comments.", "disable-comments"));
						includedRoles.text(__("No comments will be visible to other roles.", "disable-comments"));
					}
					else{
						var _selectedOptions = selectedOptions.filter(function(val) {
							return val.id !== 'logged-out-users';
						}).map(function(val, index){
							return val.id;
						});
						var text = "<b>" + _selectedOptions.join("</b>, <b>") + "</b>";
						excludedRoles.html(sprintf(__("Comments are visible to %s and <b>Logged out users</b>.", "disable-comments"), text));
						includedRoles.text(__("No comments will be visible to other roles.", "disable-comments"));
					}
				}
				else{
					var selectedOptionsLabels = selectedOptions.map(function(val, index){
						return val.text;
					});
					var text = "<b>" + selectedOptionsLabels.join("</b>, <b>") + "</b>";
					excludedRoles.html(sprintf(__("Comments are visible to %s.", "disable-comments"), text));
					includedRoles.text(__("Other roles and logged out users won't see any comments.", "disable-comments"));
				}
			}
			else{
				includedRoles.hide();
				excludedRoles.hide();
			}
		};
		excludeByRoleSelect.select2({
			multiple: true,
			data: options,
			placeholder: __("Select User Roles", "disable-comments"),
		});
		excludeByRoleSelect.on('change', selectOnChange);
		selectOnChange();
		jQuery('#enable_exclude_by_role').on('change', function(){
			if(jQuery(this).is(':checked')){
				selectDescriptionWrapper.show();
			}
			else{
				selectDescriptionWrapper.hide();
			}
		});
		jQuery('#enable_exclude_by_role').trigger('change');
	})();


	jQuery(document).on('keydown', 'label .icon[tabindex], label span[tabindex]', function(event) {
		// console.log(event);
		if (event.code === 'Space' || event.code === 'Enter') {
			event.preventDefault();

			const inputId = jQuery(this).parent().attr('for');
			const inputElement = document.getElementById(inputId);

			if (inputElement) {
				inputElement.click();
			}
		}

	});

	jQuery(document).on('keydown', '.disable__comment__nav__item a', function(event) {
		// console.log(event);
		if (event.code === 'Space' || event.code === 'Enter') {
			event.preventDefault();
			jQuery(this).click();
		}
	});

});

