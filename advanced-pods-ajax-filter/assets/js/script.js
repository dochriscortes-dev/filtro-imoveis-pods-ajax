jQuery(document).ready(function($) {

    // --- 1. Initialization ---

    // Initialize Select2 for City
    $('#apaf-cidade').select2({
        width: '100%',
        minimumResultsForSearch: 10 // Hide search if few options, keeps it clean
    });

    // Initialize Select2 for Neighborhood (Dependent)
    $('#apaf-bairro').select2({
        width: '100%',
        language: {
            noResults: function() {
                return "Selecione uma cidade primeiro";
            }
        }
    });

    // Initialize Select2 for Property Type
    $('#apaf-tipo').select2({
        width: '100%',
        closeOnSelect: false // Keep open for multiple selection
    });

    // Initialize Select2 for Zone (Modal)
    $('#apaf-zona').select2({
        width: '100%',
        minimumResultsForSearch: Infinity // Disable search for small lists
    });

    // Initialize noUiSlider for Price
    var priceSlider = document.getElementById('apaf-price-slider');
    if (priceSlider) {
        noUiSlider.create(priceSlider, {
            start: [20000, 50000000], // Start at full range
            connect: true,
            range: {
                'min': 20000, // R$ 20k
                'max': 50000000 // R$ 50M
            },
            step: 10000,
            format: {
                to: function (value) {
                    return 'R$ ' + parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                },
                from: function (value) {
                    // Remove R$, dots, spaces
                    return Number(value.replace(/[^0-9.-]+/g,""));
                }
            }
        });

        var minPriceInput = document.getElementById('apaf-input-min-price');
        var maxPriceInput = document.getElementById('apaf-input-max-price');
        var minPriceDisplay = document.getElementById('apaf-price-display-min');
        var maxPriceDisplay = document.getElementById('apaf-price-display-max');

        priceSlider.noUiSlider.on('update', function (values, handle) {
            var value = values[handle];
            var unformatted = priceSlider.noUiSlider.get(true)[handle];

            if (handle) {
                maxPriceDisplay.value = value;
                maxPriceInput.value = Math.round(unformatted);
            } else {
                minPriceDisplay.value = value;
                minPriceInput.value = Math.round(unformatted);
            }
        });
    }

    // --- 2. Smart Dependency (City -> Neighborhood) ---
    $('#apaf-cidade').on('change', function() {
        var citySlug = $(this).val();
        var $bairroSelect = $('#apaf-bairro');

        // Clear existing options
        $bairroSelect.empty();
        $bairroSelect.prop('disabled', true);

        if (citySlug) {
            $.ajax({
                url: apaf_obj.ajax_url,
                type: 'GET',
                data: {
                    action: 'apaf_get_bairros',
                    cidade_slug: citySlug,
                    nonce: apaf_obj.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(index, item) {
                            var newOption = new Option(item.text, item.id, false, false);
                            $bairroSelect.append(newOption);
                        });
                        $bairroSelect.prop('disabled', false);
                    }
                    // Trigger change to update Select2 UI
                    $bairroSelect.trigger('change');
                },
                error: function() {
                    console.log('Error fetching neighborhoods');
                }
            });
        } else {
            // If city cleared, disable bairro
            $bairroSelect.trigger('change');
        }
    });

    // --- 3. UI Interactions ---

    // Modal Open
    $('#apaf-btn-advanced').on('click', function() {
        $('#apaf-modal').addClass('is-open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden'); // Prevent scrolling
    });

    // Modal Close
    $('.apaf-modal-close, .apaf-modal-overlay').on('click', function() {
        $('#apaf-modal').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').css('overflow', '');
    });

    // Soft Square Buttons Selection
    $('.apaf-circle-buttons button').on('click', function() {
        var $btn = $(this);
        var $group = $btn.closest('.apaf-circle-buttons');
        var targetInputId = '#apaf-input-' + $group.data('target');

        // Check if already active (toggle off)
        if ($btn.hasClass('active')) {
            $btn.removeClass('active');
            $(targetInputId).val(''); // Reset
        } else {
            // Remove active class from siblings
            $group.find('button').removeClass('active');
            // Add active class to clicked
            $btn.addClass('active');
            // Set hidden input value
            $(targetInputId).val($btn.data('value'));
        }
    });

    // --- 4. Search Execution ---

    function performSearch() {
        // Collect data from Sticky Bar form
        var barData = $('#apaf-main-form').serializeArray();

        // Collect data from Modal (inputs not in main form)
        // We select inputs inside the modal container.
        var modalInputs = $('#apaf-modal :input').serializeArray();

        // Combine arrays. $.merge modifies first argument.
        var combinedData = $.merge($.merge([], barData), modalInputs);

        // Add action and nonce
        combinedData.push({name: 'action', value: 'apaf_filter_imoveis'});
        combinedData.push({name: 'nonce', value: apaf_obj.nonce});

        // Show Loader
        $('#apaf-results-loader').show();
        $('#apaf-results-container').css('opacity', '0.5');

        // Close Modal if open
        $('#apaf-modal').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').css('overflow', '');

        $.ajax({
            url: apaf_obj.ajax_url,
            type: 'POST',
            data: combinedData,
            success: function(response) {
                $('#apaf-results-loader').hide();
                $('#apaf-results-container').css('opacity', '1');

                if (response.success) {
                    $('#apaf-results-container').html(response.data.html);
                } else {
                    $('#apaf-results-container').html('<div class="apaf-no-results">Nenhum imóvel encontrado.</div>');
                }
            },
            error: function() {
                $('#apaf-results-loader').hide();
                $('#apaf-results-container').css('opacity', '1');
                $('#apaf-results-container').html('<div class="apaf-no-results">Erro de conexão. Tente novamente.</div>');
            }
        });
    }

    // Bind Search Buttons
    $('#apaf-btn-search').on('click', function(e) {
        e.preventDefault();
        performSearch();
    });

    $('#apaf-btn-apply').on('click', function(e) {
        e.preventDefault();
        performSearch();
    });

    // Optional: Trigger search on enter key in inputs?
    // Not strictly requested but good UX.
});
