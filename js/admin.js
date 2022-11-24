(function ($) {
	$.ajaxSetup({
		cache: false
	});
	$('body').on('keyup', '.mixpakk-modal', function (e) {
		if (e.key === "Escape") {
			$(this).remove();
		}
	})
	$(document).on('keydown', function (event) {
		if (event.key == "Escape") {
			$('body').css('overflow', 'auto');
			$('.mixpakk-modal').remove();
		}
	});
	$(document).on('click', '#mixpakk-save-api-licence', function () {
		var apiKey = $('#mixpakk-api-key').val();
		var licenceKey = $('#mixpakk-licence-key').val();


		if (apiKey.length > 0 && licenceKey.length > 0) {
			$.ajax({
				type: 'post',
				url: ajaxurl,
				data: {
					action: 'save_api_key_and_licence',
					licence_key: licenceKey,
					api_key: apiKey
				},
				success: function (response) {
					location.reload();
				}
			});
		}
	});


	$(document).on('click', '.mixpakk-cell', function () {
		var group_id = $(this).data('groupid');
		label_download(group_id)

	});
	$(document).on('click', '#mixpakk-close', function () {
		$('.mixpakk-modal').remove();
		$('body').css('overflow', 'auto');
	})

	function packageLog(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'view_package_log',
				group_id: group_id
			},
			success: function (response) {
				$('#package_log').html(response);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}

	function view_signature(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'view_signature',
				group_id: group_id
			},
			success: function (response) {
				var resp = JSON.parse(response);
				if (resp['img'] != '') {
					$('#mixpakk-signature').html(resp['img']);
				} else {
					$('#mixpakk-signature').remove();
				}

				packageLog(group_id);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}

	function label_download(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'label_download',
				group_id: group_id
			},
			success: function (response) {
				$('body').css('overflow', 'hidden');
				var modal = '<div class="mixpakk-modal container">';
				modal += '<div class="content">';
				modal += '<h2><span></span>' + group_id + ' csomagcsoport adatai</h2>';
				modal += '<hr>';
				modal += '<div id="package_info"></div>';
				modal += '<div id="package_log" style="text-align:center;">Loading...</div>';
				modal += '<hr>';
				modal += '<div id="mixpakk-footer">';
				modal += '<div id="mixpakk-label">' + response + '</div>';
				modal += '<div id="mixpakk-signature"></div>';
				modal += '<div id="mixpakk-close">×</div>';
				modal += '</div>';
				modal += '</div>';
				modal += '</div>';
				$('body').append(modal);
				$('.mixpakk-modal').addClass('show');
				packageView(group_id);
				view_signature(group_id);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}

	function packageView(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'package_details',
				group_id: group_id
			},
			success: function (response) {
				$('#package_info').html(response);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}
})(jQuery)
