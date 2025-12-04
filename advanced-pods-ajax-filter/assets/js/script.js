jQuery(document).ready(function($) {

	// 1. Initialize Components

	// Select2
	$('.apaf-select2').select2({
		minimumResultsForSearch: 10, // hide search if few options
		width: '100%'
	});

	// noUiSlider (Price Range)
	const priceSlider = document.getElementById('apaf-price-slider');
	if (priceSlider) {
		noUiSlider.create(priceSlider, {
			start: [20000, 50000000],
			connect: true,
			range: {
				'min': 0,
				'max': 100000000
			},
			step: 10000,
			format: {
				to: function (value) {
					return Math.round(value);
				},
				from: function (value) {
					return Number(value);
				}
			}
		});

		const minInput = document.getElementById('apaf-price-min');
		const maxInput = document.getElementById('apaf-price-max');

		// Link slider to inputs
		priceSlider.noUiSlider.on('update', function (values, handle) {
			const value = values[handle];
			if (handle === 0) {
				minInput.value = formatCurrency(value);
			} else {
				maxInput.value = formatCurrency(value);
			}
		});

		// Link inputs to slider
		minInput.addEventListener('change', function () {
			priceSlider.noUiSlider.set([parseCurrency(this.value), null]);
		});
		maxInput.addEventListener('change', function () {
			priceSlider.noUiSlider.set([null, parseCurrency(this.value)]);
		});
	}

	function formatCurrency(val) {
		return new Intl.NumberFormat('pt-BR').format(val);
	}
	function parseCurrency(val) {
		return parseInt(val.replace(/\./g, '').replace(/,/g, '.'));
	}


	// 2. UI Interactions

	// Modal Toggle
	$('#apaf-open-modal').on('click', function(e) {
		e.preventDefault();
		$('#apaf-modal-overlay').addClass('open');
		$('body').css('overflow', 'hidden'); // Prevent background scrolling
	});
	$('#apaf-close-modal, #apaf-apply-filters').on('click', function(e) {
		e.preventDefault();
		$('#apaf-modal-overlay').removeClass('open');
		$('body').css('overflow', '');
	});
	$('#apaf-modal-overlay').on('click', function(e) {
		if ($(e.target).is('#apaf-modal-overlay')) {
			$(this).removeClass('open');
			$('body').css('overflow', '');
		}
	});

	// Numeric Specs Buttons (Soft Squares)
	$('.apaf-num-buttons button').on('click', function() {
		const $btn = $(this);
		const $parent = $btn.closest('.apaf-num-buttons');
		const field = $parent.data('field');
		const val = $btn.data('val');

		// Toggle logic: if clicking active, unselect. If clicking other, switch active.
		if ($btn.hasClass('active')) {
			$btn.removeClass('active');
			$('#apaf-' + field + '-input').val('');
		} else {
			$parent.find('button').removeClass('active');
			$btn.addClass('active');
			$('#apaf-' + field + '-input').val(val);
		}
	});


	// 3. Logic: City -> Neighborhood Dependency (AJAX)

	$('#apaf-city-select').on('change', function() {
		const citySlug = $(this).val();
		const $neighborhoodSelect = $('#apaf-neighborhood-select');

		if (!citySlug) {
			$neighborhoodSelect.prop('disabled', true).html('<option value="">Bairro</option>').trigger('change');
			return;
		}

		$neighborhoodSelect.prop('disabled', true); // Disable while loading

		$.ajax({
			url: apaf_obj.ajax_url,
			type: 'POST',
			data: {
				action: 'apaf_get_bairros',
				nonce: apaf_obj.nonce,
				cidade: citySlug
			},
			success: function(response) {
				if (response.success) {
					let options = '<option value="">Bairro</option>';
					$.each(response.data, function(index, item) {
						options += `<option value="${item.id}">${item.text}</option>`;
					});
					$neighborhoodSelect.html(options).prop('disabled', false).trigger('change');
				} else {
					console.error('Error fetching neighborhoods:', response);
					$neighborhoodSelect.prop('disabled', false); // Re-enable even if empty? maybe leave disabled if empty.
				}
			},
			error: function() {
				console.error('AJAX error');
				$neighborhoodSelect.prop('disabled', false);
			}
		});
	});


	// 4. Logic: Search / Filter (AJAX)

	function doSearch(paged = 1) {
		const $results = $('#apaf-results');
		$results.css('opacity', '0.5'); // Visual loading state

		// Gather Data
		const data = {
			action: 'apaf_filter_imoveis',
			nonce: apaf_obj.nonce,
			paged: paged,
			// Main Bar
			finalidade: $('input[name="finalidade"]:checked').val(),
			cidade: $('#apaf-city-select').val(),
			bairro: $('#apaf-neighborhood-select').val(),
			// Modal
			tipo_imovel: [],
			min_price: parseCurrency($('#apaf-price-min').val() || '0'),
			max_price: parseCurrency($('#apaf-price-max').val() || '0'),
			quartos: $('#apaf-quartos-input').val(),
			banheiros: $('#apaf-banheiros-input').val(),
			vagas: $('#apaf-vagas-input').val(),
			zona: $('#apaf-zona-select').val(),
			aceita_financiamento: $('input[name="aceita_financiamento"]').is(':checked') ? 1 : 0
		};

		// Gather Checkboxes
		$('input[name="tipo_imovel[]"]:checked').each(function() {
			data.tipo_imovel.push($(this).val());
		});

		$.ajax({
			url: apaf_obj.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {
				$results.html(response).css('opacity', '1');
			},
			error: function() {
				$results.html('<p>Ocorreu um erro na busca.</p>').css('opacity', '1');
			}
		});
	}

	// Trigger Search
	$('#apaf-search-btn').on('click', function() {
		doSearch(1);
	});

	// Apply filters from modal also triggers search
	$('#apaf-apply-filters').on('click', function() {
		doSearch(1);
	});

	// Pagination Click (Delegated)
	$(document).on('click', '.apaf-pagination a', function(e) {
		e.preventDefault();
		const link = $(this).attr('href');
		// Extract paged arg from link or just assume normal WP structure
		// Using a simplified regex to find paged parameter if clean URL
		// Or if format is ?paged=N
		let paged = 1;
		const match = link.match(/paged=(\d+)/) || link.match(/\/page\/(\d+)/);
		if (match) {
			paged = match[1];
		}

		doSearch(paged);

		// Scroll to top of results
		$('html, body').animate({
			scrollTop: $("#apaf-results").offset().top - 100
		}, 500);
	});

});
