jQuery(document).ready(function($) {
    // === UI Initialization ===

    // Initialize Select2 for Neighborhoods
    var $bairroSelect = $('#apaf-bairro').select2({
        placeholder: 'Bairro',
        allowClear: true,
        // dropdownParent: $('#apaf-search-bar') // Optional, but usually fine appended to body
    });

    // Operation Type Toggle
    $('.apaf-toggle-btn').on('click', function(e) {
        // Prevent default only if it interferes (radio inside label usually handles itself)
        // But we want to update the visual class 'active'
        $('.apaf-toggle-btn').removeClass('active');
        $(this).addClass('active');
    });

    // === Modal Logic ===
    var $modal = $('#apaf-modal');
    var $openModalBtn = $('#apaf-open-modal');
    var $closeModalBtn = $('.apaf-close-modal');
    var $applyFiltersBtn = $('#apaf-apply-filters');

    // Function to ensure slider renders correctly when modal opens
    function refreshSlider() {
        var slider = document.getElementById('apaf-price-slider');
        if (slider && slider.noUiSlider) {
            // Little timeout to ensure display:flex has applied
            setTimeout(function() {
                slider.noUiSlider.on('update', function(){}); // Trigger update to be safe?
                // Actually .reset() or just accessing it often fixes it.
                // There is no explicit refresh() in v15, but accessing dimensions works if visible.
                // The issue with hidden sliders is usually the handle position calculation.
                // We can reset the range or set values to current values to force redraw.
                // Or simply:
                // slider.noUiSlider.destroy();
                // createSlider(); // Re-init
                // But typically updateOptions is the way.
                // slider.noUiSlider.updateOptions({}, true); // fireSet = true
            }, 50);
        }
    }

    $openModalBtn.on('click', function(e) {
        e.preventDefault();
        $modal.addClass('active');
        $('body').css('overflow', 'hidden'); // Prevent background scrolling
        refreshSlider();
    });

    function closeModal() {
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }

    $closeModalBtn.on('click', closeModal);

    // Close on click outside
    $(window).on('click', function(e) {
        if ($(e.target).is($modal)) {
            closeModal();
        }
    });

    // Apply Filters triggers search
    $applyFiltersBtn.on('click', function() {
        closeModal();
        triggerSearch();
    });


    // === Price Slider ===
    var slider = document.getElementById('apaf-price-slider');
    var minPriceDisplay = document.getElementById('apaf-price-min-display');
    var maxPriceDisplay = document.getElementById('apaf-price-max-display');
    var minPriceInput = document.getElementById('apaf-min-price');
    var maxPriceInput = document.getElementById('apaf-max-price');

    // Range: 20k to 10M
    var minRange = 20000;
    var maxRange = 10000000;

    if (slider) {
        noUiSlider.create(slider, {
            start: [minRange, maxRange],
            connect: true,
            range: {
                'min': minRange,
                'max': maxRange
            },
            step: 5000,
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
            var formatted = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(value);

            if (handle) {
                if (maxPriceDisplay) maxPriceDisplay.innerHTML = formatted;
                if (maxPriceInput) maxPriceInput.value = value;
            } else {
                if (minPriceDisplay) minPriceDisplay.innerHTML = formatted;
                if (minPriceInput) minPriceInput.value = value;
            }
        });
    }

    // === Dependency: City -> Neighborhood ===
    $('#apaf-cidade').on('change', function() {
        var citySlug = $(this).val();
        $bairroSelect.empty();

        if (!citySlug) {
            $bairroSelect.prop('disabled', true);
            return;
        }

        $bairroSelect.prop('disabled', true);

        $.ajax({
            url: apaf_obj.ajax_url,
            type: 'GET',
            data: {
                action: 'apaf_get_bairros',
                cidade: citySlug
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    if (data.length > 0) {
                         $.each(data, function(index, item) {
                             var newOption = new Option(item.text, item.id, false, false);
                             $bairroSelect.append(newOption);
                         });
                         $bairroSelect.prop('disabled', false);
                         $bairroSelect.trigger('change');
                    }
                }
            },
            complete: function() {
                // If failed or empty, maybe enable but empty?
                // Keeping it disabled if empty is fine.
            }
        });
    });

    // === Search Execution ===
    function triggerSearch() {
        // 1. Data from Main Bar
        var barData = $('#apaf-form').serializeArray();

        // 2. Data from Modal
        // Zone
        var zone = $('#apaf-zona').val();
        if(zone) barData.push({ name: 'zona', value: zone });

        // Price
        barData.push({ name: 'min_price', value: $('#apaf-min-price').val() });
        barData.push({ name: 'max_price', value: $('#apaf-max-price').val() });

        // Radios (Quartos, Banheiros, Vagas)
        // Find checked in modal
        var quartos = $('input[name="quartos"]:checked').val();
        if (quartos) barData.push({ name: 'quartos', value: quartos });

        var banheiros = $('input[name="banheiros"]:checked').val();
        if (banheiros) barData.push({ name: 'banheiros', value: banheiros });

        var vagas = $('input[name="vagas"]:checked').val();
        if (vagas) barData.push({ name: 'vagas', value: vagas });

        // Financing
        if ($('#apaf-aceita-financiamento').is(':checked')) {
            barData.push({ name: 'aceita_financiamento', value: 1 });
        }

        // Add action and nonce
        barData.push({ name: 'action', value: 'filter_imoveis' });
        barData.push({ name: 'nonce', value: apaf_obj.nonce });

        // UI Loading
        $('#apaf-loading').fadeIn();
        $('.apaf-results').css('opacity', '0.5');

        // Request
        $.ajax({
            url: apaf_obj.ajax_url,
            type: 'POST',
            data: $.param(barData),
            success: function(response) {
                if(response.success) {
                    $('#apaf-results-container').html(response.data.html);
                } else {
                    $('#apaf-results-container').html('<p>Erro ao carregar resultados.</p>');
                }
            },
            error: function(err) {
                $('#apaf-results-container').html('<p>Erro de conexão.</p>');
            },
            complete: function() {
                $('#apaf-loading').fadeOut();
                $('.apaf-results').css('opacity', '1');
            }
        });
    }

    // Bind Search Button in Bar
    $('#apaf-search-btn').on('click', function(e) {
        e.preventDefault();
        triggerSearch();
    });

});
