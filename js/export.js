(function ($) {
	$(document).ready(function () {

		$(document).on('click', '.mixpakk_csv_export_btn', function (e) {
			var currentURL = document.URL;
			var csvURL = currentURL.split('edit.php')[0] + 'edit.php?post_type=shop_order&generate_deliveo_csv=';

			deliveoSend(csvURL)
		});

	});
})(jQuery);