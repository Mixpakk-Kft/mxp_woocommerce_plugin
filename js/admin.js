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
		$('#the-list input[type="checkbox"][name="post[]"]:checked, #the-list input[type="checkbox"][name="id[]"]:checked').each(function(index) 
		{
			orders.push($(this).val());
		});
		var download = (orders.length > 25);
		
		while (orders.length != 0)
		{
			orders = await getLabels(orders, download);
		}
		$('#mixpakk_print_labels').removeAttr('disabled');
	});

	/**
	 * CODES RESPONSIBLE FOR BOTH WOOCOMMERCE ADMIN ORDER LIST AND ADMIN ORDER PAGE
	 */

    function heartbeat_add_data(event, data) 
    {
        let ids = $('.mixpakk-submitting').map(function()
        {
            return $(this).attr("data-order-id");
        }).get();

        if (ids.length == 0)
        {
            $(document).unbind('heartbeat-send', heartbeat_add_data);
            $(document).unbind('heartbeat-tick', heartbeat_process_data);
            return;
        }

        data.mixpakkPendingStatus = ids;
    }

    function heartbeat_process_data(event, data)
    {
        if (!data.mixpakkUpdateStatus) 
        {
            return;
        }

        for (const [order_id, group_code] of Object.entries(data.mixpakkUpdateStatus)) 
        {
			let placeholder = $(`.mixpakk-submitting[data-order-id="${order_id}"]`);
            if (group_code == null)
            {
                $(`[name="m_option[${order_id}]"].mixpakk-busy, [name="m_unit[${order_id}]"].mixpakk-busy, #mxp-submit.mixpakk-busy`).removeClass('mixpakk-busy');
				$(`#mxp-submit`).prop('disabled', false);
				$(`#mxp-groupid-delete`).remove();
                $(`#order-${order_id}, #post-${order_id}`).addClass('mixpakk-failed');
                placeholder.remove();
            }
            else
            {
                let dom_elem = $(`
                    <span class="mixpakk-cell no-link" style="cursor: pointer;font-weight:600;color:#0073aa;" data-groupid="${group_code}">${group_code}</span>
                `);
				$(`[name="m_option[${order_id}]"].mixpakk-busy, [name="m_unit[${order_id}]"].mixpakk-busy, #mxp-submit`).remove();
                $(`#mxp-groupid-delete`).removeClass('mixpakk-busy');
                $(`#order-${order_id}, #post-${order_id}`).addClass('mixpakk-success');

				placeholder.replaceWith(dom_elem);
            }
        }
    };

	$(document).on('click', '#mxp-groupid-delete', function (event) 
	{
		event.preventDefault();

		let elem = $(this);
		elem.prop('disabled', true);

		$.ajax(
			{
				type: "POST",
				url: ajaxurl,
				data: {
					action: 'mixpakk_delete_order',
					nonce: $('#mxp_nonce').val(),
					order: $('#post_ID').val(),
				},
				success: function (response) 
				{
					if (response.data.dom !== undefined)
					{
						$('#mxp_deliveo_id_box .inside').html(response.data.dom);
					}
				},
				complete: function ()
				{
					elem.prop('disabled', false);
				}
			}
		);
	});

	$(document).on('click', '#mxp-submit', function (event) 
	{
		event.preventDefault();
		let order_id = $('#post_ID').val();

		let post_data = {
			action: 'mixpakk_submit_order',
			nonce: $('#mxp_nonce').val(),
			order: order_id,
			m_option: {},
			m_unit: {},
		};

		post_data.m_option[order_id] = $(`[name="m_option[${order_id}]"]`).val();
		post_data.m_unit[order_id] = $(`[name="m_unit[${order_id}]"]`).val() ?? null;

		let elem = $(this);
		elem.prop('disabled', true);

		$.ajax(
			{
				type: "POST",
				url: ajaxurl,
				data: post_data,
				success: function (response) 
				{
					$(document).unbind('heartbeat-send', heartbeat_add_data);
					$(document).unbind('heartbeat-tick', heartbeat_process_data);

					if (response.data.dom !== undefined)
					{
						$('#mxp_deliveo_id_box .inside').html(response.data.dom);
						if ($('.mixpakk-submitting').size() > 0)
						{
							$(document).on('heartbeat-tick', heartbeat_process_data);
							$(document).on('heartbeat-send', heartbeat_add_data);
							wp.heartbeat.interval('fast');
						}
					}
				},
				complete: function ()
				{
					elem.prop('disabled', false);
				}
			}
		);
	});

	$(document).on('keydown', 'input.mxp-packaging-unit', function (event) 
	{
		if (event.key == 'Enter') 
		{
			event.preventDefault();
		}
	});

	if ($('.mixpakk-submitting').size() > 0)
	{
		$(document).on('heartbeat-tick', heartbeat_process_data);
		$(document).on('heartbeat-send', heartbeat_add_data);
        wp.heartbeat.interval('fast');
	}
})(jQuery)
