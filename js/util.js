/*
 * Utility javascript functions to abstrace UI elements from the
 * underlying framework. 
/* Display a popup notification */
var shop_spinner = null;

var Shop = (function() {
	return {
		// Display a notification popup for a short time.
		notify: function(message, status='', timeout=1500) {
			if (status == 'success') {
				var icon = "<i class='uk-icon uk-icon-check'></i>&nbsp;";
			} else if (status == 'warning') {
				var icon = '<i class="uk-icon uk-icon-exclamation-triangle"></i>&nbsp';
			} else {
				var icon = '';
			}
			if (typeof UIkit.notify === 'function') {
				// uikit v2 theme
	            UIkit.notify(icon + message, {timeout: timeout});
			} else if (typeof UIkit.notification === 'function') {
		        // uikit v3 theme
				UIkit.notification({
		            message: icon + message,
				    timeout: timeout,
		            status: status,
				});
		    } else {
				alert(message);
			}
		},
		modal: function(content="") {
			if (typeof(UIkit.modal.blockUI) == 'function') {
				var modal = UIkit.modal.blockUI(content);
			} else if (typeof(UIkit.modal.dialog) == 'function') {
				content = '<div class="uk-modal-body uk-text-large uk-text-center">' + content + '&nbsp;&nbsp;</div>';
				var modal = UIkit.modal.dialog(content, {'bgClose':false});
			}
		},
		// Display a spinner in a modal dialog during processing.
		spinner_show: function (message="") {
			var content = '<div class="uk-text-large uk-text-center"><i class="uk-icon-spinner uk-icon-large uk-icon-spin"></i>&nbsp;' + message + '</div>';
			if (typeof(UIkit.modal.blockUI) == 'function') {
				shop_spinner = UIkit.modal.blockUI(
					content,
					{center:true}
				);
				shop_spinner.show();
			} else if (typeof(UIkit.modal.dialog) == 'function') {
				content = '<div class="uk-modal-body uk-text-large uk-text-center" uk-spinner>' + message + '&nbsp;&nbsp;</div>';
				shop_spinner = UIkit.modal.dialog(content, {'bgClose':false});
			}
		},
		// Hide the spinner dialog created above.
		spinner_hide: function() {
			if (shop_spinner != null) {
				if (typeof(shop_spinner.hide) == 'function') {
					shop_spinner.hide();
				}
			}
		}
	};
})();

