<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apaf_search_shortcode() {
	// Get terms for drop-downs
	$cidades = get_terms( array(
		'taxonomy'   => 'cidade',
		'hide_empty' => true,
	) );

	// Get terms for property types (checkbox grid)
	$tipos = get_terms( array(
		'taxonomy'   => 'tipo_imovel',
		'hide_empty' => false, // Show all types even if empty? Usually better to hide empty, but prompt implies 'Display all'. I'll stick to hide_empty=false for now.
	) );

	ob_start();
	?>
	<div id="apaf-root">

		<!-- 1. STICKY BAR -->
		<div id="apaf-search-bar">

			<!-- Operation Toggle -->
			<div class="apaf-group apaf-operation">
				<div class="apaf-toggle-wrapper">
					<input type="radio" name="finalidade" id="op-comprar" value="comprar" checked>
					<label for="op-comprar">Comprar</label>

					<input type="radio" name="finalidade" id="op-alugar" value="alugar">
					<label for="op-alugar">Alugar</label>

					<div class="apaf-toggle-bg"></div>
				</div>
			</div>

			<!-- City Select -->
			<div class="apaf-group apaf-city">
				<select id="apaf-city-select" name="cidade" class="apaf-select2" data-placeholder="Cidade">
					<option value="">Cidade</option> <!-- Placeholder -->
					<?php if ( ! empty( $cidades ) && ! is_wp_error( $cidades ) ) : ?>
						<?php foreach ( $cidades as $cidade ) : ?>
							<option value="<?php echo esc_attr( $cidade->slug ); ?>"><?php echo esc_html( $cidade->name ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Neighborhood Select -->
			<div class="apaf-group apaf-neighborhood">
				<select id="apaf-neighborhood-select" name="bairro" class="apaf-select2" data-placeholder="Bairro" disabled>
					<option value="">Bairro</option>
				</select>
			</div>

			<!-- Advanced Filters Link -->
			<div class="apaf-group apaf-advanced-link">
				<a href="#" id="apaf-open-modal">
					<span class="dashicons dashicons-filter"></span> Filtros Avançados
				</a>
			</div>

			<!-- Search Button -->
			<div class="apaf-group apaf-submit">
				<button type="button" id="apaf-search-btn">BUSCAR</button>
			</div>

		</div>

		<!-- 2. ADVANCED MODAL -->
		<div id="apaf-modal-overlay">
			<div id="apaf-modal">
				<div class="apaf-modal-header">
					<h3>Filtros Avançados</h3>
					<button type="button" id="apaf-close-modal">&times;</button>
				</div>

				<div class="apaf-modal-body">

					<!-- A. Property Types -->
					<div class="apaf-section">
						<h4>Tipo de Imóvel</h4>
						<div class="apaf-checkbox-grid">
							<?php if ( ! empty( $tipos ) && ! is_wp_error( $tipos ) ) : ?>
								<?php foreach ( $tipos as $tipo ) : ?>
									<label class="apaf-checkbox-card">
										<input type="checkbox" name="tipo_imovel[]" value="<?php echo esc_attr( $tipo->slug ); ?>">
										<span><?php echo esc_html( $tipo->name ); ?></span>
									</label>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>

					<!-- B. Price Range -->
					<div class="apaf-section">
						<h4>Faixa de Preço</h4>
						<div id="apaf-price-slider"></div>
						<div class="apaf-price-inputs">
							<div class="apaf-input-wrapper">
								<span>R$</span>
								<input type="text" id="apaf-price-min" placeholder="Mínimo">
							</div>
							<div class="apaf-input-wrapper">
								<span>R$</span>
								<input type="text" id="apaf-price-max" placeholder="Máximo">
							</div>
						</div>
					</div>

					<!-- C. Numeric Specs -->
					<div class="apaf-section">
						<div class="apaf-specs-row">
							<div class="apaf-spec-group">
								<h4>Quartos</h4>
								<div class="apaf-num-buttons" data-field="quartos">
									<button type="button" data-val="1">1+</button>
									<button type="button" data-val="2">2+</button>
									<button type="button" data-val="3">3+</button>
									<button type="button" data-val="4">4+</button>
								</div>
								<input type="hidden" name="quartos" id="apaf-quartos-input">
							</div>

							<div class="apaf-spec-group">
								<h4>Banheiros</h4>
								<div class="apaf-num-buttons" data-field="banheiros">
									<button type="button" data-val="1">1+</button>
									<button type="button" data-val="2">2+</button>
									<button type="button" data-val="3">3+</button>
									<button type="button" data-val="4">4+</button>
								</div>
								<input type="hidden" name="banheiros" id="apaf-banheiros-input">
							</div>

							<div class="apaf-spec-group">
								<h4>Vagas</h4>
								<div class="apaf-num-buttons" data-field="vagas">
									<button type="button" data-val="1">1+</button>
									<button type="button" data-val="2">2+</button>
									<button type="button" data-val="3">3+</button>
									<button type="button" data-val="4">4+</button>
								</div>
								<input type="hidden" name="vagas" id="apaf-vagas-input">
							</div>
						</div>
					</div>

					<!-- D. Zone -->
					<div class="apaf-section">
						<h4>Zona</h4>
						<select name="zona" id="apaf-zona-select">
							<option value="">Todas</option>
							<option value="urbana">Urbana</option>
							<option value="rural">Rural</option>
						</select>
					</div>

					<!-- E. Financing -->
					<div class="apaf-section">
						<label class="apaf-toggle-check">
							<input type="checkbox" name="aceita_financiamento" value="1">
							<span class="slider"></span>
							<span class="label-text">Aceita Financiamento</span>
						</label>
					</div>

				</div> <!-- .apaf-modal-body -->

				<div class="apaf-modal-footer">
					<button type="button" id="apaf-apply-filters">APLICAR FILTROS</button>
				</div>
			</div>
		</div>

		<!-- Results Container -->
		<div id="apaf-results">
			<!-- Results will be loaded here via AJAX -->
		</div>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'pods_advanced_filter', 'apaf_search_shortcode' );
