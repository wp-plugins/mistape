(function($) {

	var Deco_Mistape = {

		onReady: function () {

			Deco_Mistape.dlg = new DialogFx(document.getElementById('mistape_dialog'));

			$(document).keyup(function (ev) {
				if (ev.keyCode === 13 && ev.ctrlKey && ev.target.nodeName.toLowerCase() !== 'textarea') {
					var report = Deco_Mistape.getSelectionParentElement();
					if (report.selection.length > 0 && report.selection.length < 200 ) {
						Deco_Mistape.reportSpellError(report);
					}
				}
			});
		},

		getSelectionParentElement: function() {
			var parentEl, sel;
			if (window.getSelection) {
				sel = window.getSelection();
				if (sel.rangeCount) {
					parentEl = sel.getRangeAt(0).commonAncestorContainer;
					if (parentEl.nodeType != 1) {
						parentEl = parentEl.parentNode;
					}
				}
				sel = sel.toString();
			} else if ( (sel = document.selection) && sel.type != 'Control') {
				parentEl = sel.createRange().parentElement();
				sel = sel.createRange().text;
			}
			parentEl = parentEl.innerText;
			return { 'selection': sel, 'context': parentEl };
		},

		reportSpellError: function(report) {
			var nonce = mistapeArgs.nonce;
			if ( report.hasOwnProperty('selection') && report.hasOwnProperty('context') && nonce) {
				Deco_Mistape.dlg.toggle();
				$.ajax({
					type: 'post',
					dataType: 'json',
					url: mistapeArgs.ajaxurl,
					data: {
						action: 'mistape_report_error',
						reported_text: report.selection,
						context: report.context,
						nonce: nonce
					},
					/*success: function (response) {
						// for later use
					}*/
				})
			}
		}
	};

	$( document ).ready( Deco_Mistape.onReady );

})(jQuery);
