jQuery(document).ready(function($) {
    // === UI Logic ===

    // Initialize Select2 for Neighborhoods
    var $bairroSelect = $('#apaf-bairro').select2({
        placeholder: 'Bairro', // Clean placeholder
        allowClear: true,
        dropdownParent: $('#apaf-form') // Ensure it attaches to the bar form initially, though dropdownParent is mostly for modals.
    });

    // Operation Type Toggle
    $('.apaf-toggle-btn').on('click', function() {
        $('.apaf-toggle-btn').removeClass('active');
        $(this).addClass('active');
        // The radio input inside will be checked automatically by browser behavior, but we can force trigger if needed.
    });

    // Modal Handling
    var $modal = $('#apaf-modal');
    var $openModalBtn = $('#apaf-open-modal');
    var $closeModalBtn = $('.apaf-close-modal');
    var $applyFiltersBtn = $('#apaf-apply-filters');

    $openModalBtn.on('click', function(e) {
        e.preventDefault();
        $modal.addClass('active');
        $('body').css('overflow', 'hidden'); // Prevent background scrolling
    });

    function closeModal() {
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }

    $closeModalBtn.on('click', closeModal);

    $(window).on('click', function(e) {
        if ($(e.target).is($modal)) {
            closeModal();
        }
    });

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
            step: 10000,
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
                maxPriceDisplay.innerHTML = formatted;
                maxPriceInput.value = value;
            } else {
                minPriceDisplay.innerHTML = formatted;
                minPriceInput.value = value;
            }
        });
    }

    // === Dependency Logic: City -> Neighborhood ===
    $('#apaf-cidade').on('change', function() {
        var citySlug = $(this).val();

        // Clear current neighborhoods
        $bairroSelect.empty();

        if (!citySlug) {
            $bairroSelect.prop('disabled', true);
            return;
        }

        // Disable while loading
        $bairroSelect.prop('disabled', true);

        // AJAX to fetch bairros
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
                    // Populate Select2
                    // data is array of {id, text}
                    if (data.length > 0) {
                         // Add empty option or placeholder if needed, but Select2 handles placeholder.
                         // Need to iterate and add options.
                         // Select2 expects data format if using 'data' option in init, but here we are appending options manually.
                         $.each(data, function(index, item) {
                             var newOption = new Option(item.text, item.id, false, false);
                             $bairroSelect.append(newOption);
                         });
                         $bairroSelect.prop('disabled', false);
                         $bairroSelect.trigger('change'); // Notify Select2
                    } else {
                        // No neighborhoods found
                        // Keep disabled or show "No neighborhoods"
                    }
                } else {
                    console.error('Error fetching bairros:', response);
                }
            },
            error: function(err) {
                console.error('AJAX error:', err);
            }
        });
    });

    // === Search Logic ===
    function triggerSearch() {
        // Collect data from Bar Form
        var barData = $('#apaf-form').serializeArray();

        // Collect data from Modal Fields (manually)
        // Price
        barData.push({ name: 'min_price', value: $('#apaf-min-price').val() });
        barData.push({ name: 'max_price', value: $('#apaf-max-price').val() });

        // Radios (Quartos, Banheiros, Vagas)
        // We need to find the checked ones in the modal
        var quartos = $('input[name="quartos"]:checked').val();
        if (quartos) barData.push({ name: 'quartos', value: quartos });

        var banheiros = $('input[name="banheiros"]:checked').val();
        if (banheiros) barData.push({ name: 'banheiros', value: banheiros });

        var vagas = $('input[name="vagas"]:checked').val();
        if (vagas) barData.push({ name: 'vagas', value: vagas });

        // Checkbox (Financing)
        if ($('#apaf-aceita-financiamento').is(':checked')) {
            barData.push({ name: 'aceita_financiamento', value: 1 });
        }

        // Add action and nonce
        barData.push({ name: 'action', value: 'filter_imoveis' });
        barData.push({ name: 'nonce', value: apaf_obj.nonce });

        // Show loading
        $('#apaf-loading').fadeIn();
        $('.apaf-results').css('opacity', '0.5');

        $.ajax({
            url: apaf_obj.ajax_url,
            type: 'POST',
            data: $.param(barData), // Convert array to query string
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

    // Bind Search Button
    $('#apaf-search-btn').on('click', function(e) {
        e.preventDefault();
        triggerSearch();
    });

});
