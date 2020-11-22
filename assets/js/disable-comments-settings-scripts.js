jQuery(document).ready(function () {
	if (jQuery("#disablecommentswrap").length) {
		var tabNavItem =
			"ul.disable__comment__nav li.disable__comment__nav__item";
		var tabBodyItem = ".disable__comment__tab .disable__comment__tab__item";
		jQuery(tabNavItem + "> a").on("click", function () {
			if (jQuery(tabNavItem + " a").hasClass("active")) {
				jQuery(tabNavItem + " a").removeClass("active");
			}
			jQuery(this).toggleClass("active");
			jQuery(tabBodyItem).toggleClass("show");
		});
	}
});
