(function($) {
	$(document).ready(function() {

        $(document).on('click', '.mixpakk_settings_save', function() {
            var textInputs = $('#mixpakk-settings-form .required');
            var validationMessages = $('.validation-messages');
            var errorMessage = '';
            var valid = true;

            $.each(textInputs, function(index, item) {
                var inputField = $(item);
                var inputValue = inputField.val().trim();

                inputField.removeClass('invalid');

                if ( inputValue.length == 0 ) {
                    valid = false;
                    errorMessage += inputField.attr('data-message') + '<br />';
                    inputField.addClass('invalid');
                }
            });


            if (valid) {
                return true;
            } else {
                validationMessages.html(errorMessage);
                validationMessages.removeClass('hidden');

                return false;
            }
        })

    });
})(jQuery);
