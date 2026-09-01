jQuery(document).ready(function ($) {
	var __        = wp.i18n.__;
	var _e        = wp.i18n._e;
	var sprintf   = wp.i18n.sprintf;
	var $form     = jQuery('#disableCommentSaveSettings');
	var saveBtn   = jQuery("#disableCommentSaveSettings button.button.button__success");
	var deleteBtn = jQuery("#deleteCommentSettings button.button.button__delete");
	var savedData;
	var networkAjaxUrl = disableCommentsObj.is_network_admin === '1'
		? ajaxurl + (ajaxurl.indexOf('?') === -1 ? '?' : '&') + 'is_network_admin=1'
		: ajaxurl;

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
				dataSource             : networkAjaxUrl,
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
		// Selected by class rather than by .siblings(). The two settings tabs
		// share one <form>, so the panels are no longer all siblings of each
		// other — .siblings() would leave the Delete panel showing underneath.
		var showTab = function (target) {
			if (!target || !jQuery(target).length) {
				return false;
			}
			jQuery(tabBodyItem).removeClass("show");
			jQuery(target).addClass("show");
			jQuery(tabNavItem + " > a").removeClass("active").attr("aria-selected", "false");
			jQuery(tabNavItem + ' > a[href="' + target + '"]')
				.addClass("active")
				.attr("aria-selected", "true");
			return true;
		};

		jQuery(tabNavItem).on("click", "a", function (e) {
			e.preventDefault();
			showTab(jQuery(this).attr("href"));
		});

		// "#delete" is the alias the Tools menu redirects to; it predates the
		// panel ids and is a documented entry point, so it keeps working.
		if (hash === "#delete") {
			showTab("#deleteComments");
		} else if (hash) {
			// Any panel can be deep-linked by its own id. showTab() ignores a
			// hash that names no panel, so an unrelated fragment still lands on
			// the default tab rather than a blank screen.
			showTab(hash);
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
			url: networkAjaxUrl,
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
					jQuery(document).trigger('disableComments:saved');
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
	// Preview: how many comments would this delete remove?
	(function(){
		var form   = jQuery("#deleteCommentSettings");
		var button = jQuery("#preview_delete_comments");
		var output = jQuery("#delete_preview_result");

		if (!button.length) {
			return;
		}

		// A count that no longer matches the form is worse than no count: someone
		// can preview one post type, switch to "Everywhere", and confirm while
		// the screen still shows the smaller number. The preview is cleared on
		// any change to the form, and a late response for a payload that is no
		// longer current is discarded.
		form.on("change input", function(){
			output.empty();
		});

		button.on("click", function(){
			var btn      = jQuery(this);
			var payload  = form.serialize();

			btn.prop("disabled", true);
			output.empty().append(jQuery("<p></p>").text(__("Counting…", "disable-comments")));

			jQuery.post(networkAjaxUrl, {
				action: "disable_comments_preview_delete",
				nonce:  disableCommentsObj._nonce,
				data:   payload
			}).done(function(response){
				if (form.serialize() !== payload) {
					// The form moved while this was in flight.
					output.empty();
					return;
				}

				if (!response || !response.success || !response.data) {
					output.empty().append(
						jQuery('<p class="notice notice-error"></p>').text(
							__("Could not count the comments.", "disable-comments")
						)
					);
					return;
				}

				output.empty();

				var total = jQuery('<p class="notice notice-warning"></p>').text(
					__("This will permanently delete", "disable-comments") + " " + response.data.total + " " +
					__("comment(s). There is no undo.", "disable-comments")
				);
				output.append(total);

				var list = jQuery("<ul></ul>");
				jQuery.each(response.data.breakdown, function(label, count){
					// .text(): labels come from post type and comment type
					// names, which plugins control.
					list.append(jQuery("<li></li>").text(label + ": " + count));
				});
				output.append(list);

				// The button promises "what will be deleted", so show the
				// comments rather than only how many of them there are. A
				// count cannot be reviewed, and this delete has no undo.
				var sample = response.data.sample || [];

				if (sample.length) {
					output.append(jQuery('<p class="delete-preview-heading"></p>').text(
						sample.length < response.data.total
							? sprintf(
								/* translators: 1: how many comments are listed, 2: how many will be deleted. */
								__("Showing %1$s of the %2$s, newest first:", "disable-comments"),
								sample.length,
								response.data.total
							)
							: __("All of them:", "disable-comments")
					));

					var rows = jQuery('<ul class="delete-preview-comments"></ul>');

					jQuery.each(sample, function(_, item){
						// .text() throughout: an author name, a post title and
						// a comment body are all attacker-controlled strings.
						var meta = item.date + " · " + item.post;

						if (item.site) {
							meta += " · " + item.site;
						}

						rows.append(
							jQuery("<li></li>")
								.append(jQuery('<span class="delete-preview-author"></span>').text(item.author))
								.append(jQuery('<span class="delete-preview-meta"></span>').text(meta))
								.append(jQuery('<span class="delete-preview-excerpt"></span>').text(item.excerpt))
						);
					});

					output.append(rows);
				}
			}).fail(function(){
				output.empty().append(
					jQuery('<p class="notice notice-error"></p>').text(
						__("Could not reach the server.", "disable-comments")
					)
				);
			}).always(function(){
				btn.prop("disabled", false);
			});
		});

		// The export is a file download, so it goes through a real form POST
		// rather than XHR — the browser has to be the one receiving it.
		jQuery("#export_comments_before_delete").on("click", function(){
			var post = jQuery("<form></form>")
				.attr("method", "post")
				.attr("action", networkAjaxUrl)
				.css("display", "none");

			post.append(jQuery("<input>").attr({ type: "hidden", name: "action", value: "disable_comments_export_comments" }));
			post.append(jQuery("<input>").attr({ type: "hidden", name: "nonce" }).val(disableCommentsObj._nonce));
			post.append(jQuery("<input>").attr({ type: "hidden", name: "data" }).val(form.serialize()));

			jQuery("body").append(post);
			post.trigger("submit");
			post.remove();
		});
	})();

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
				jQuery.post(networkAjaxUrl, data, function (response) {
					deleteBtn.html(__("Delete Comments", "disable-comments"));
					if (response.success) {
						// The count rides along with the event so listeners can
						// tell a delete that removed something from one that
						// matched nothing — the two are otherwise identical
						// from here, and the difference decides whether there
						// is any aftermath worth re-rendering.
						var deleted = (response.data && response.data.deleted)
							? parseInt(response.data.deleted, 10)
							: 0;

						jQuery(document).trigger('disableComments:deleted', [deleted]);
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
						var escapedOptions = _selectedOptions.map(function(label) {
							return $('<span>').text(label).html();
						});
						var text = "<b>" + escapedOptions.join("</b>, <b>") + "</b>";
						excludedRoles.html(sprintf(__("Comments are visible to %s and <b>Logged out users</b>.", "disable-comments"), text));
						includedRoles.text(__("No comments will be visible to other roles.", "disable-comments"));
					}
				}
				else{
					var selectedOptionsLabels = selectedOptions.map(function(val, index){
						return val.text;
					});
					var escapedLabels = selectedOptionsLabels.map(function(label) {
						return $('<span>').text(label).html();
					});
					var text = "<b>" + escapedLabels.join("</b>, <b>") + "</b>";
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

	// Conditional rules: reveal the fields, and search terms on demand so the
	// picker never offers terms from another taxonomy — or runs out of them.
	(function(){
		var fields = jQuery('#conditional_rules_fields');
		var toggle = jQuery('#enable_conditional_rules');

		if (!toggle.length) {
			return;
		}

		toggle.on('change', function(){
			if (jQuery(this).is(':checked')) {
				fields.removeAttr('hidden').show();
			} else {
				fields.hide();
			}
		});
		toggle.trigger('change');

		var taxonomySelect = jQuery('#conditional_rule_taxonomy');
		var termsSelect    = jQuery('#conditional_rule_terms');

		// The picker used to be handed one fixed list of the taxonomy's first 200
		// terms. On a taxonomy larger than that, everything after those 200 could
		// not be chosen for a new rule at all — so it asks the server as the user
		// types, a page at a time, and there is no ceiling left to raise.
		if (termsSelect.length && jQuery.fn.select2) {
			termsSelect.select2({
				multiple: true,
				width: '100%',
				placeholder: __('Search terms', 'disable-comments'),
				ajax: {
					url: networkAjaxUrl,
					type: 'POST',
					dataType: 'json',
					// Typing is not a query per keystroke.
					delay: 250,
					data: function (params) {
						return {
							action:   'disable_comments_get_terms',
							nonce:    disableCommentsObj._nonce,
							taxonomy: taxonomySelect.val(),
							search:   params.term || '',
							page:     params.page || 1
						};
					},
					processResults: function (response, params) {
						params.page = params.page || 1;

						if (!response || !response.success || !response.data) {
							return { results: [] };
						}

						// Two quick changes of taxonomy can land out of order, and
						// a late reply would offer the previous taxonomy's terms —
						// which then save as term IDs that do not belong to the
						// taxonomy now selected. The server says what it answered
						// for, so a stale reply can be dropped.
						if (response.data.taxonomy !== taxonomySelect.val()) {
							return { results: [] };
						}

						return {
							// Term names are user-supplied. select2 escapes result
							// text by default, and escapeMarkup is deliberately not
							// overridden here so it stays that way.
							results: jQuery.map(response.data.terms || [], function (term) {
								return { id: term.id, text: term.name };
							}),
							// Scrolling the dropdown asks for the next page.
							pagination: { more: !!response.data.more }
						};
					}
				}
			});
		}

		taxonomySelect.on('change', function () {
			// A term ID means nothing outside the taxonomy it came from, so a
			// switch drops the selection rather than carrying numbers across.
			// Nothing is loaded to replace them: the next search does that.
			termsSelect.val(null).empty().trigger('change');
		});
	})();

	// Reset the blocked-attempt counters.
	(function(){
		var button = jQuery('#reset_blocked_stats');

		if (!button.length) {
			return;
		}

		button.on('click', function(){
			var btn = jQuery(this);

			btn.prop('disabled', true);

			jQuery.post(networkAjaxUrl, {
				action: 'disable_comments_reset_blocked_stats',
				nonce:  disableCommentsObj._nonce
			}).done(function(response){
				if (!response || !response.success || !response.data) {
					return;
				}

				jQuery('.blocked-attempts-count').each(function(){
					var vector = jQuery(this).data('vector');
					jQuery(this).text(response.data.counts[vector] || 0);
				});

				jQuery('#blocked_attempts_summary').text(
					__('Counters reset. Counting starts again from now.', 'disable-comments')
				);
			}).always(function(){
				btn.prop('disabled', false);
			});
		});
	})();

	// The WooCommerce review panel is a server-rendered snapshot. After an AJAX
	// save it would keep claiming the state from page load — saying reviews are
	// enabled moments after the save that disabled them. Recompute it from the
	// form rather than leaving a stale sentence on screen.
	(function(){
		var panel = jQuery('#woocommerce_reviews_wrapper');

		if (!panel.length) {
			return;
		}

		jQuery(document).on('disableComments:saved', function(){
			var state = panel.find('[data-dc-review-state]');

			// WooCommerce's own review switch is not on this form, so it cannot
			// be recomputed from these controls. When it is what is blocking
			// reviews, the server-rendered sentence is the only accurate one —
			// replacing it would claim reviews are enabled when the store still
			// refuses them.
			if (state.data('wc-blocked') === 1 || state.data('wc-blocked') === '1') {
				return;
			}

			var everywhere = jQuery('input[name="mode"]:checked').val() === 'remove_everywhere';
			var product    = jQuery('input[name="disabled_types[]"][value="product"]').is(':checked');

			state.text(
				( everywhere || product )
					? __('Product reviews are currently disabled.', 'disable-comments')
					: __('Product reviews are currently enabled.', 'disable-comments')
			);
		});
	})();

	// Bringing the review prompt on screen after a delete earns it.
	//
	// Dismissing it is NOT handled here — that lives in review-prompt.js, which
	// is enqueued wherever the notice can render rather than only on this
	// screen. Doing it in both places would post the dismissal twice.
	(function(){
		var prompt = jQuery('#disable_comments_review_prompt');

		// The prompt is armed by a successful bulk delete, but the page was
		// rendered before that delete happened — so on the very run that earns
		// it, there is no notice in the DOM. Reload once so the server can
		// render it, which is also the moment it is meant to appear.
		jQuery(document).on('disableComments:deleted', function(event, deleted){
			// Only a delete that actually removed something can arm the
			// prompt: record_review_trigger() ignores a count below one. A run
			// that matched nothing has nothing new for the server to render,
			// so reloading the whole screen would be a full page refresh for
			// no result.
			if (!deleted) {
				return;
			}

			if (prompt.length) {
				return;
			}

			window.setTimeout(function(){
				window.location.reload();
			}, 3200);
		});
	})();

	// Theme conflict scanner.
	(function(){
		var button = jQuery('#run_theme_scan');
		var output = jQuery('#theme_scan_result');

		if (!button.length) {
			return;
		}

		button.on('click', function(){
			var btn = jQuery(this);

			btn.prop('disabled', true);
			output.empty().append(
				jQuery('<p></p>').text(__('Checking your site…', 'disable-comments'))
			);

			jQuery.post(networkAjaxUrl, {
				action: 'disable_comments_scan_theme',
				nonce:  disableCommentsObj._nonce
			}).done(function(response){
				var data = response && response.data ? response.data : {};
				// Every message is built with .text(): it can carry a theme name
				// and a file path, both of which are untrusted strings.
				var message = jQuery('<p></p>').text(data.message || __('Could not check your site.', 'disable-comments'));

				output.empty().append(message);

				if (data.verdict === 'clean') {
					message.addClass('notice notice-success');
				} else if (data.verdict) {
					message.addClass('notice notice-warning');
				} else {
					message.addClass('notice notice-info');
				}

				// A cached response is the most common reason a scan says
				// something surprising, so say when one was served.
				if (data.x_cache) {
					output.append(
						jQuery('<p class="disable__option__description"></p>').text(
							__('Your host served this page from cache (x-cache: ', 'disable-comments') + data.x_cache + ').'
						)
					);
				}
			}).fail(function(jqXHR){
				// "No published content has comments disabled yet" comes back
				// as a 400 with an actionable message, which jQuery routes here
				// rather than to .done(). Discarding it and saying the scanner
				// was unreachable sends the user looking for a network fault
				// instead of at their own settings.
				var body    = jqXHR && jqXHR.responseJSON;
				var message = (body && body.data && body.data.message)
					? body.data.message
					: __('Could not reach the scanner.', 'disable-comments');

				output.empty().append(
					jQuery('<p class="notice notice-warning"></p>').text(message)
				);
			}).always(function(){
				btn.prop('disabled', false);
			});
		});
	})();

	// Settings export / import.
	(function(){
		var exportBtn  = jQuery('#export_dc_settings');
		var fileInput  = jQuery('#import_dc_settings_file');
		var fileName   = jQuery('#import_dc_settings_filename');
		var previewBtn = jQuery('#import_dc_settings_preview');
		var applyBtn   = jQuery('#import_dc_settings_apply');
		var hint       = jQuery('#import_dc_settings_hint');
		var output     = jQuery('#import_dc_settings_result');
		var payload    = null;

		if (!exportBtn.length) {
			return;
		}

		// Preview and Apply both start disabled, and each unlocks a step later
		// than you would guess: preview needs the file to have finished
		// reading, apply needs a preview that actually found changes. Without a
		// line saying so, two dead buttons on arrival read as a broken panel.
		var setHint = function (text) {
			hint.text(text);
		};

		// A download has to be a real navigation, not XHR.
		exportBtn.on('click', function(){
			var form = jQuery('<form></form>')
				.attr('method', 'post')
				.attr('action', networkAjaxUrl)
				.css('display', 'none');

			form.append(jQuery('<input>').attr({ type: 'hidden', name: 'action', value: 'disable_comments_export_settings' }));
			form.append(jQuery('<input>').attr({ type: 'hidden', name: 'nonce' }).val(disableCommentsObj._nonce));

			jQuery('body').append(form);
			form.trigger('submit');
			form.remove();
		});

		fileInput.on('change', function(){
			payload = null;
			previewBtn.prop('disabled', true);
			applyBtn.prop('disabled', true);
			output.empty();

			var file = this.files && this.files[0];

			if (!file) {
				fileName.text(__('No file chosen', 'disable-comments'));
				setHint(__('Choose a settings file to preview what it would change.', 'disable-comments'));
				return;
			}

			// .text(): a file name is whatever the user named it.
			fileName.text(file.name);
			setHint(__('Reading the file…', 'disable-comments'));

			var reader = new FileReader();
			reader.onload = function(e){
				payload = e.target.result;

				// Enabled here rather than on `change`, because the payload
				// arrives asynchronously: a fast click in between would hit
				// the "choose a settings file first" guard on a file that had
				// just been chosen, which is exactly the kind of thing that
				// makes the panel look broken.
				previewBtn.prop('disabled', false);
				setHint(__('Now preview the file to see what would change.', 'disable-comments'));
			};
			reader.onerror = function(){
				setHint(__('That file could not be read. Try choosing it again.', 'disable-comments'));
			};
			reader.readAsText(file);
		});

		var send = function(dryRun){
			if (!payload) {
				output.empty().append(
					jQuery('<p class="notice notice-warning"></p>').text(
						__('Choose a settings file first.', 'disable-comments')
					)
				);
				return;
			}

			output.empty().append(jQuery('<p></p>').text(__('Checking the file…', 'disable-comments')));

			jQuery.post(networkAjaxUrl, {
				action:  'disable_comments_import_settings',
				nonce:   disableCommentsObj._nonce,
				payload: payload,
				dry_run: dryRun ? 1 : 0
			}).done(function(response){
				output.empty();

				if (!response || !response.success) {
					var message = (response && response.data && response.data.message)
						? response.data.message
						: __('That file could not be imported.', 'disable-comments');
					// .text(): the message can quote the file's own contents.
					output.append(jQuery('<p class="notice notice-error"></p>').text(message));
					applyBtn.prop('disabled', true);
					setHint(__('Fix the file, then preview it again.', 'disable-comments'));
					return;
				}

				var data    = response.data || {};
				var changes = data.changes || {};
				var count   = 0;
				var list    = jQuery('<ul></ul>');

				// Shown before the no-change return below: an import that
				// requests only unregistered post types has an empty diff and a
				// populated unknown list, and "this site already matches that
				// file" would hide the fact that none of it will take effect.
				if (data.unknown_post_types && data.unknown_post_types.length) {
					output.append(jQuery('<p class="notice notice-warning"></p>').text(
						__('Not registered on this site, so they will have no effect:', 'disable-comments') +
						' ' + data.unknown_post_types.join(', ')
					));
				}

				jQuery.each(changes, function(key, change){
					count++;
					list.append(jQuery('<li></li>').text(
						key + ': ' + JSON.stringify(change.from) + ' → ' + JSON.stringify(change.to)
					));
				});

				if (!count) {
					output.append(jQuery('<p class="notice notice-info"></p>').text(
						__('This site already matches that file. Nothing to change.', 'disable-comments')
					));
					applyBtn.prop('disabled', true);
					// The notice says what happened; the hint says what to do
					// next. Every other branch splits them that way, and this
					// one used to say the same sentence twice, ten pixels
					// apart.
					setHint(__('Choose another file to compare.', 'disable-comments'));
					return;
				}

				output.append(jQuery('<p></p>').text(
					data.applied
						? __('Imported. These settings changed:', 'disable-comments')
						: __('These settings would change:', 'disable-comments')
				));
				output.append(list);

				applyBtn.prop('disabled', !!data.applied);
				setHint(
					data.applied
						? __('Imported. Choose another file to import again.', 'disable-comments')
						: __('Review the changes below, then apply them.', 'disable-comments')
				);
			}).fail(function(jqXHR){
				// Invalid JSON, an unsupported schema version and permission
				// failures all come back as 400/403, which lands here rather
				// than in .done(). Showing "could not reach the server" would
				// hide the one message that tells the user what to do.
				var body    = jqXHR && jqXHR.responseJSON;
				var message = (body && body.data && body.data.message)
					? body.data.message
					: __('Could not reach the server.', 'disable-comments');

				output.empty().append(
					jQuery('<p class="notice notice-error"></p>').text(message)
				);
				applyBtn.prop('disabled', true);
				setHint(__('Fix the file, then preview it again.', 'disable-comments'));
			});
		};

		previewBtn.on('click', function(){ send(true); });
		applyBtn.on('click', function(){ send(false); });
	})();

	// Handle allowed comment types toggle
	(function(){
		var allowedCommentTypesWrapper = jQuery('#allowed_comment_types_wrapper');
		jQuery('#enable_allowed_comment_types').on('change', function(){
			if(jQuery(this).is(':checked')){
				allowedCommentTypesWrapper.show();
			}
			else{
				allowedCommentTypesWrapper.hide();
				// Uncheck all comment type checkboxes when disabled
				allowedCommentTypesWrapper.find('input[type="checkbox"]').prop('checked', false);
			}
		});
		jQuery('#enable_allowed_comment_types').trigger('change');
	})();

	// Handle blocked comment types toggle
	(function(){
		var blockedCommentTypesWrapper = jQuery('#blocked_comment_types_wrapper');
		jQuery('#enable_blocked_comment_types').on('change', function(){
			if(jQuery(this).is(':checked')){
				blockedCommentTypesWrapper.show();
			}
			else{
				blockedCommentTypesWrapper.hide();
				// Uncheck all comment type checkboxes when disabled
				blockedCommentTypesWrapper.find('input[type="checkbox"]').prop('checked', false);
			}
		});
		jQuery('#enable_blocked_comment_types').trigger('change');
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

