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

	async function getLabels(orders, download)
	{
		await $.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'mixpakk_generate_labels',
				post: orders
			},
			success: function (response) {
				console.info(response);
				if (response.result == 0)
				{
					const b64toBlob = (b64Data, contentType='', sliceSize=512) => {
						const byteCharacters = atob(b64Data);
						const byteArrays = [];
					  
						for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
							const slice = byteCharacters.slice(offset, offset + sliceSize);
						
							const byteNumbers = new Array(slice.length);
							for (let i = 0; i < slice.length; i++) {
								byteNumbers[i] = slice.charCodeAt(i);
							}
						
							const byteArray = new Uint8Array(byteNumbers);
							byteArrays.push(byteArray);
						}
					  
						const blob = new Blob(byteArrays, {type: contentType});
						return blob;
					}

					var blob = b64toBlob(response.data.content, 'application/pdf');
					
					var URL = window.URL || window.webkitURL;
					var downloadUrl = URL.createObjectURL(blob);

					if (!download)
					{
						document.getElementById('mixpakk_label_print_preview').onload = function() 
						{
							document.getElementById('mixpakk_label_print_preview').contentWindow.focus(); 
							document.getElementById('mixpakk_label_print_preview').contentWindow.print();
						};
						
						$('#mixpakk_label_print_preview').attr('src', downloadUrl);
						
						URL.revokeObjectURL(downloadUrl);
					}
					else
					{
						if (window.navigator.msSaveOrOpenBlob) 
						{
							window.navigator.msSaveOrOpenBlob(file, filename);
						}
						else
						{
							var a = document.createElement("a")
							a.href = downloadUrl;
							a.download = 'labels_' + new Date().toISOString() + '.pdf';
							document.body.appendChild(a);
							a.click();
							setTimeout(function() {
								document.body.removeChild(a);
								URL.revokeObjectURL(downloadUrl);
							}, 0); 
						}
					}
					orders = response.data.remaining;
				}
				else
				{
					if (response.data.remaining)
					{
						orders = response.data.remaining;
					}
					else
					{
						orders = [];
					}
				}
			},
			error: function (errorThrown) {
				orders = [];
				console.warn(errorThrown);
			}
		});

		return orders;
	}

	$('#mixpakk_print_labels').on('click', async function (event) 
	{
		$('#mixpakk_print_labels').attr('disabled', '');
		var orders = [];
		$('#the-list input[type="checkbox"][name="post[]"]:checked').each(function(index) 
		{
			orders.push($(this).val());
		});
		var download = (orders.length > 25);
		
		while (orders.length != 0)
		{
			orders = await getLabels(orders, download);
		}
		$('#mixpakk_print_labels').removeAttr('disabled');
		//.finally(() => {$('#mixpakk_print_labels').removeAttr('disabled');})
	});

})(jQuery)
