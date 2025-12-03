jQuery(document).ready(function($) {
    // Initialize Select2 for Neighborhoods
    $('#apaf-bairro').select2({
        placeholder: 'Selecione os bairros',
        allowClear: true
    });

    // Initialize noUiSlider for Price
    var slider = document.getElementById('apaf-price-slider');
    var minPriceDisplay = document.getElementById('apaf-price-min-display');
    var maxPriceDisplay = document.getElementById('apaf-price-max-display');
    var minPriceInput = document.getElementById('apaf-min-price');
    var maxPriceInput = document.getElementById('apaf-max-price');

    // Default range (could be passed via localize script for better accuracy)
    var minRange = 0;
    var maxRange = 5000000;

    noUiSlider.create(slider, {
        start: [minRange, maxRange],
        connect: true,
        range: {
            'min': minRange,
            'max': maxRange
        },
        step: 1000,
        format: {
            to: function (value) {
                return parseInt(value);
            },
            from: function (value) {
                return Number(value);
            }
        }
    });

    slider.noUiSlider.on('update', function (values, handle) {
        var value = values[handle];

        // Format for display
        var formatted = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

        if (handle) {
            maxPriceDisplay.innerHTML = formatted;
            maxPriceInput.value = value;
        } else {
            minPriceDisplay.innerHTML = formatted;
            minPriceInput.value = value;
        }
    });

    // Handle Search
    $('#apaf-search-btn').on('click', function(e) {
        e.preventDefault();

        var form = $('#apaf-form');
        var formData = form.serialize();

        // Add action and nonce
        formData += '&action=filter_imoveis&nonce=' + apaf_obj.nonce;

        // Show loading
        $('#apaf-loading').fadeIn();
        $('.apaf-results').css('opacity', '0.5');

        $.ajax({
            url: apaf_obj.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if(response.success) {
                    $('#apaf-results-container').html(response.data.html);
                } else {
                    $('#apaf-results-container').html('<p>Erro ao carregar resultados.</p>');
                    console.error(response);
                }
            },
            error: function(err) {
                $('#apaf-results-container').html('<p>Erro de conexão.</p>');
                console.error(err);
            },
            complete: function() {
                $('#apaf-loading').fadeOut();
                $('.apaf-results').css('opacity', '1');
            }
        });
    });

    // Handle Clear Filters
    $('#apaf-clear-btn').on('click', function(e) {
        e.preventDefault();

        var form = $('#apaf-form');
        form[0].reset();

        // Reset Select2
        $('#apaf-bairro').val(null).trigger('change');

        // Reset Slider
        slider.noUiSlider.set([minRange, maxRange]);

        // Trigger search to reset results (optional, or just clear results)
        // $('#apaf-search-btn').trigger('click');
        // Or clear results container
        $('#apaf-results-container').html('');
    });
});
