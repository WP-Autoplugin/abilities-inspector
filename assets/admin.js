(function($){
	function applyStriping() {
		$('.abex-wrap .wp-list-table').each(function(){
			var $table = $(this);
			var $rows = $table.find('tbody tr').not('.abex-details-row');

			$rows.addClass('abex-row').removeClass('abex-alt');
			$rows.each(function(index){
				if (index % 2 === 0) {
					$(this).addClass('abex-alt');
				}
			});
		});
	}

	function prettyJSON(obj) {
		try {
			if (obj === null || typeof obj === 'undefined' || obj === '') return '';
			if (typeof obj === 'string') {
				// Might already be JSON or plain text.
				try { return JSON.stringify(JSON.parse(obj), null, 2); } catch(e) { return obj; }
			}
			return JSON.stringify(obj, null, 2);
		} catch(e) {
			return '';
		}
	}

	function renderPanel(data) {
		var rest = (typeof data.show_in_rest === 'boolean') ? (data.show_in_rest ? 'true' : 'false') : '—';
		var status = data.disabled ? 'Disabled' : 'Enabled';
		var desc = data.description ? $('<div/>').text(data.description).html() : '—';

		var html = '';
		html += '<div class="abex-grid">';
		html += '  <div>';
		html += '    <dl class="abex-kv">';
		html += '      <dt>Name</dt><dd><code>' + $('<div/>').text(data.name).html() + '</code></dd>';
		html += '      <dt>Label</dt><dd>' + $('<div/>').text(data.label || '—').html() + '</dd>';
		html += '      <dt>Description</dt><dd>' + desc + '</dd>';
		html += '    </dl>';
		html += '  </div>';
		html += '  <div>';
		html += '    <dl class="abex-kv">';
		html += '      <dt>Category</dt><dd>' + $('<div/>').text(data.category_label || data.category || '—').html() + '</dd>';
		html += '      <dt>show_in_rest</dt><dd><code>' + rest + '</code></dd>';
		html += '      <dt>Status</dt><dd><code>' + status + '</code></dd>';
		html += '    </dl>';
		html += '  </div>';
		html += '</div>';

		var ann = prettyJSON(data.annotations);
		var inp = prettyJSON(data.input_schema);
		var out = prettyJSON(data.output_schema);

		if (ann) {
			html += '<div style="margin-top:12px;"><strong>Annotations</strong><pre class="abex-pre"></pre></div>';
		}
		if (inp) {
			html += '<div style="margin-top:12px;"><strong>Input schema</strong><pre class="abex-pre"></pre></div>';
		}
		if (out) {
			html += '<div style="margin-top:12px;"><strong>Output schema</strong><pre class="abex-pre"></pre></div>';
		}

		html += '<div class="abex-note">Note: Some fields may appear blank if the registering plugin does not provide them or if the Abilities object does not expose them as public accessors.</div>';

		var $wrap = $('<div class="abex-details-content"></div>').html(html);

		var preIndex = 0;
		if (ann) { $wrap.find('pre.abex-pre').eq(preIndex++).text(ann); }
		if (inp) { $wrap.find('pre.abex-pre').eq(preIndex++).text(inp); }
		if (out) { $wrap.find('pre.abex-pre').eq(preIndex++).text(out); }

		return $wrap;
	}

	$(document).on('click', '.abex-details', function(e){
		e.preventDefault();

		var $btn = $(this);
		var $row = $btn.closest('tr');
		var $detailsRow = $row.next('.abex-details-row');
		var $panelHost = $detailsRow.find('.abex-details-panel');

		// Close other open panels in the table.
		$('.abex-details-row:visible').not($detailsRow).hide().prev().find('.abex-details').removeClass('is-open');

		if ($detailsRow.is(':visible')) {
			$detailsRow.hide();
			$btn.removeClass('is-open');
			applyStriping();
			return;
		}

		var encoded = $btn.data('ability');
		try {
			var json = atob(encoded);
			var data = JSON.parse(json);
			$panelHost.empty().append(renderPanel(data));
		} catch(err) {
			$panelHost.empty().append('<div class="abex-details-panel">Could not render details.</div>');
		}

		$detailsRow.show();
		$btn.addClass('is-open');
		applyStriping();
	});

	$(applyStriping);
})(jQuery);
