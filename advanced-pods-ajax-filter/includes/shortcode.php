<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apaf_shortcode_render() {
	// Get Taxonomy Terms
	$cidades = get_terms( array(
		'taxonomy'   => 'cidade',
		'hide_empty' => false,
	) );

	// We load all bairros initially, but JS will clear/filter them
	// Or we can load none and let JS populate.
	// The prompt says "When a City is selected... dynamically populate".
	// So initially, if no city is selected, maybe we show none or all?
	// Usually clean UX starts with empty or all. Let's start with empty or generic placeholder.
	// But to be safe and allow non-JS fallback or initial search, maybe loading all is okay?
	// The prompt implies dependency. I'll leave the initial loading empty or placeholder.
	$bairros = array(); // Load dynamically

	$tipos_imovel = get_terms( array(
		'taxonomy'   => 'tipo_imovel',
		'hide_empty' => false,
	) );

	ob_start();
	?>
	<div class="apaf-wrapper">

		<!-- Main Filter Bar -->
		<form id="apaf-form" class="apaf-bar-form">

			<!-- Operation Type (Toggle) -->
			<div class="apaf-bar-item apaf-toggle-group">
				<label class="apaf-toggle-btn active">
					<input type="radio" name="finalidade" value="venda" checked> Comprar
				</label>
				<label class="apaf-toggle-btn">
					<input type="radio" name="finalidade" value="locacao"> Alugar
				</label>
			</div>

			<!-- City -->
			<div class="apaf-bar-item">
				<select name="cidade" id="apaf-cidade" class="apaf-minimal-select">
					<option value="">Cidade</option>
					<?php if ( ! empty( $cidades ) && ! is_wp_error( $cidades ) ) : ?>
						<?php foreach ( $cidades as $cidade ) : ?>
							<option value="<?php echo esc_attr( $cidade->slug ); ?>"><?php echo esc_html( $cidade->name ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Neighborhood (Select2) -->
			<div class="apaf-bar-item apaf-bar-wide">
				<select name="bairro[]" id="apaf-bairro" class="apaf-select2" multiple="multiple" style="width: 100%;" disabled>
					<!-- Populated via AJAX -->
				</select>
			</div>

			<!-- Property Type -->
			<div class="apaf-bar-item">
				<select name="tipo_imovel[]" id="apaf-tipo-imovel" class="apaf-minimal-select">
					<option value="">Tipo de Imóvel</option>
					<?php if ( ! empty( $tipos_imovel ) && ! is_wp_error( $tipos_imovel ) ) : ?>
						<?php foreach ( $tipos_imovel as $tipo ) : ?>
							<option value="<?php echo esc_attr( $tipo->slug ); ?>"><?php echo esc_html( $tipo->name ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Search Button -->
			<div class="apaf-bar-item">
				<button type="button" id="apaf-search-btn" class="apaf-btn-dark">
					<span class="apaf-search-icon">🔍</span>
				</button>
			</div>
		</form>

		<!-- Advanced Filter Trigger -->
		<div class="apaf-advanced-trigger-wrapper">
			<a href="#" id="apaf-open-modal">Filtro Avançado</a>
		</div>

		<!-- Advanced Modal -->
		<div id="apaf-modal" class="apaf-modal">
			<div class="apaf-modal-content">
				<div class="apaf-modal-header">
					<h3>Filtros Avançados</h3>
					<span class="apaf-close-modal">&times;</span>
				</div>
				<div class="apaf-modal-body">
					<!-- Fields that are part of the same form logically, but physically separate.
					     We can either move them into the form via JS or append them to the form data.
					     To keep it simple, we will wrap everything in one form?
					     No, the bar is a specific layout. We can use the 'form' attribute on inputs if supported,
					     or just collect data from both containers in JS. I will choose the JS collection method. -->

					<!-- Price Slider -->
					<div class="apaf-modal-field">
						<label>Valor do Imóvel</label>
						<div id="apaf-price-slider"></div>
						<div class="apaf-price-values">
							<span id="apaf-price-min-display"></span> - <span id="apaf-price-max-display"></span>
						</div>
						<!-- Hidden inputs to store slider values -->
						<input type="hidden" id="apaf-min-price">
						<input type="hidden" id="apaf-max-price">
					</div>

					<!-- Dorms -->
					<div class="apaf-modal-field">
						<label>Dormitórios</label>
						<div class="apaf-radio-group-boxes">
							<label><input type="radio" name="quartos" value="1"> 1</label>
							<label><input type="radio" name="quartos" value="2"> 2</label>
							<label><input type="radio" name="quartos" value="3"> 3</label>
							<label><input type="radio" name="quartos" value="4+"> 4+</label>
						</div>
					</div>

					<!-- Baths -->
					<div class="apaf-modal-field">
						<label>Banheiros</label>
						<div class="apaf-radio-group-boxes">
							<label><input type="radio" name="banheiros" value="1"> 1</label>
							<label><input type="radio" name="banheiros" value="2"> 2</label>
							<label><input type="radio" name="banheiros" value="3"> 3</label>
							<label><input type="radio" name="banheiros" value="4+"> 4+</label>
						</div>
					</div>

					<!-- Vagas -->
					<div class="apaf-modal-field">
						<label>Vagas</label>
						<div class="apaf-radio-group-boxes">
							<label><input type="radio" name="vagas" value="1"> 1</label>
							<label><input type="radio" name="vagas" value="2"> 2</label>
							<label><input type="radio" name="vagas" value="3"> 3</label>
							<label><input type="radio" name="vagas" value="4+"> 4+</label>
						</div>
					</div>

					<!-- Financing -->
					<div class="apaf-modal-field">
						<label class="apaf-checkbox-label">
							<input type="checkbox" id="apaf-aceita-financiamento" value="1">
							Aceita Financiamento?
						</label>
					</div>

				</div>
				<div class="apaf-modal-footer">
					<button type="button" id="apaf-apply-filters" class="apaf-btn-primary">Aplicar Filtros</button>
				</div>
			</div>
		</div>

		<!-- Loading -->
		<div id="apaf-loading" style="display:none;">
			<div class="apaf-spinner"></div>
		</div>

		<!-- Results Container -->
		<div id="apaf-results-container" class="apaf-results">
			<!-- Results will be loaded here via AJAX -->
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'pods_advanced_filter', 'apaf_shortcode_render' );
