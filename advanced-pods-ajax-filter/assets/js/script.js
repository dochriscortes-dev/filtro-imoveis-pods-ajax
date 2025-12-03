jQuery(document).ready(function($) {

    // --- 1. Initialization ---

    // Initialize Select2 for City
    $('#apaf-cidade').select2({
        width: '100%'
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
    // No special handling needed for single select vs multiple, Select2 adapts to the select element
    $('#apaf-tipo').select2({
        width: '100%'
    });

    // Initialize noUiSlider for Price
    var priceSlider = document.getElementById('apaf-price-slider');
    if (priceSlider) {
        noUiSlider.create(priceSlider, {
            start: [0, 50000000],
            connect: true,
            range: {
                'min': 0,
                'max': 50000000
            },
            step: 10000,
            format: {
                to: function (value) {
                    return 'R$ ' + parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                },
                from: function (value) {
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
                maxPriceInput.value = unformatted; // Store raw number
            } else {
                minPriceDisplay.value = value;
                minPriceInput.value = unformatted;
            }
        });
    }

    // --- 2. Smart Dependency (City -> Neighborhood) ---
    // Logic: Neighborhood is disabled by default. Enabled only when city has value.

    // Ensure disabled state on load (if city empty)
    if (!$('#apaf-cidade').val()) {
        $('#apaf-bairro').prop('disabled', true);
    }

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
                    } else {
                         // No neighborhoods found or error
                         // Optional: Add a placeholder option "No neighborhoods found"
                    }
                    // Trigger change to update Select2 UI
                    $bairroSelect.trigger('change');
                },
                error: function() {
                    console.log('Error fetching neighborhoods');
                }
            });
        } else {
             // City cleared, keep neighborhood disabled and cleared
             $bairroSelect.trigger('change');
        }
    });

    // --- 3. UI Interactions ---

    // Toggle Button Logic (Operation) is handled by radio inputs, but let's ensure styling works.
    // CSS uses :checked, so pure CSS handles visual state.

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

    // Circular Buttons Selection
    $('.apaf-circle-buttons button').on('click', function() {
        var $btn = $(this);
        var $group = $btn.closest('.apaf-circle-buttons');
        var targetInputId = '#apaf-input-' + $group.data('target');

        // Remove active class from siblings
        $group.find('button').removeClass('active');

        // Add active class to clicked
        $btn.addClass('active');

        // Set hidden input value
        $(targetInputId).val($btn.data('value'));
    });

    // --- 4. Search Execution ---

    function performSearch() {
        var formData = $('#apaf-main-form').serialize();
        // Also include fields from modal if they are outside main form?
        // Wait, the main form structure in PHP wraps ONLY the sticky bar.
        // The modal fields are outside `<form id="apaf-main-form">`.
        // I need to gather data from BOTH.

        // Let's create a combined data object
        var barData = $('#apaf-main-form').serializeArray();

        // Modal inputs are not in a form tag in the markup I wrote, or they are just in divs.
        // Let's manually gather them or wrap modal in a form too?
        // Better to select inputs by name from the modal container.
        var modalInputs = $('#apaf-modal :input').serializeArray();

        // Combine
        var combinedData = $.merge(barData, modalInputs);

        // Convert to object or keep as array? jQuery ajax data can take array.
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
                    $('#apaf-results-container').html('<p>Erro ao buscar imóveis.</p>');
                }
            },
            error: function() {
                $('#apaf-results-loader').hide();
                $('#apaf-results-container').css('opacity', '1');
                $('#apaf-results-container').html('<p>Erro de conexão.</p>');
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

});
