<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apaf_shortcode_render() {
	// Get Taxonomy Terms
	$cidades = get_terms( array(
		'taxonomy'   => 'cidade',
		'hide_empty' => false, // Set to true if you only want terms with posts
	) );

	$bairros = get_terms( array(
		'taxonomy'   => 'bairro',
		'hide_empty' => false,
	) );

	$tipos_imovel = get_terms( array(
		'taxonomy'   => 'tipo_imovel',
		'hide_empty' => false,
	) );

	ob_start();
	?>
	<div class="apaf-wrapper">
		<div class="apaf-filter-form">
			<h3>Filtros</h3>
			<form id="apaf-form">

				<!-- Cidade -->
				<div class="apaf-field">
					<label for="apaf-cidade">Cidade</label>
					<select name="cidade" id="apaf-cidade" class="apaf-select">
						<option value="">Selecione uma cidade</option>
						<?php if ( ! empty( $cidades ) && ! is_wp_error( $cidades ) ) : ?>
							<?php foreach ( $cidades as $cidade ) : ?>
								<option value="<?php echo esc_attr( $cidade->slug ); ?>"><?php echo esc_html( $cidade->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>

				<!-- Bairro -->
				<div class="apaf-field">
					<label for="apaf-bairro">Bairro</label>
					<select name="bairro[]" id="apaf-bairro" class="apaf-select2" multiple="multiple" style="width: 100%;">
						<?php if ( ! empty( $bairros ) && ! is_wp_error( $bairros ) ) : ?>
							<?php foreach ( $bairros as $bairro ) : ?>
								<option value="<?php echo esc_attr( $bairro->slug ); ?>"><?php echo esc_html( $bairro->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>

				<!-- Tipo do Imóvel -->
				<div class="apaf-field">
					<label>Tipo do Imóvel</label>
					<div class="apaf-checkbox-group">
						<?php if ( ! empty( $tipos_imovel ) && ! is_wp_error( $tipos_imovel ) ) : ?>
							<?php foreach ( $tipos_imovel as $tipo ) : ?>
								<label>
									<input type="checkbox" name="tipo_imovel[]" value="<?php echo esc_attr( $tipo->slug ); ?>">
									<?php echo esc_html( $tipo->name ); ?>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Dormitórios -->
				<div class="apaf-field">
					<label>Dormitórios</label>
					<div class="apaf-radio-group">
						<label><input type="radio" name="quartos" value="0"> 0</label>
						<label><input type="radio" name="quartos" value="1"> 1</label>
						<label><input type="radio" name="quartos" value="2"> 2</label>
						<label><input type="radio" name="quartos" value="3"> 3</label>
						<label><input type="radio" name="quartos" value="4+"> 4+</label>
					</div>
				</div>

				<!-- Banheiros -->
				<div class="apaf-field">
					<label>Banheiros</label>
					<div class="apaf-radio-group">
						<label><input type="radio" name="banheiros" value="0"> 0</label>
						<label><input type="radio" name="banheiros" value="1"> 1</label>
						<label><input type="radio" name="banheiros" value="2"> 2</label>
						<label><input type="radio" name="banheiros" value="3"> 3</label>
						<label><input type="radio" name="banheiros" value="4+"> 4+</label>
					</div>
				</div>

				<!-- Vagas -->
				<div class="apaf-field">
					<label>Vagas</label>
					<div class="apaf-radio-group">
						<label><input type="radio" name="vagas" value="0"> 0</label>
						<label><input type="radio" name="vagas" value="1"> 1</label>
						<label><input type="radio" name="vagas" value="2"> 2</label>
						<label><input type="radio" name="vagas" value="3"> 3</label>
						<label><input type="radio" name="vagas" value="4+"> 4+</label>
					</div>
				</div>

				<!-- Valor do Imóvel -->
				<div class="apaf-field">
					<label>Valor do Imóvel</label>
					<div id="apaf-price-slider"></div>
					<div class="apaf-price-values">
						<span id="apaf-price-min-display"></span> - <span id="apaf-price-max-display"></span>
					</div>
					<input type="hidden" name="min_price" id="apaf-min-price">
					<input type="hidden" name="max_price" id="apaf-max-price">
				</div>

				<!-- Financia? -->
				<div class="apaf-field">
					<label>
						<input type="checkbox" name="aceita_financiamento" value="1">
						Aceita Financiamento?
					</label>
				</div>

				<!-- Buttons -->
				<div class="apaf-buttons">
					<button type="button" id="apaf-search-btn" class="apaf-btn apaf-btn-primary">Buscar</button>
					<button type="button" id="apaf-clear-btn" class="apaf-btn apaf-btn-secondary">Limpar Filtros</button>
				</div>

				<!-- Loading -->
				<div id="apaf-loading" style="display:none;">Carregando...</div>
			</form>
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
