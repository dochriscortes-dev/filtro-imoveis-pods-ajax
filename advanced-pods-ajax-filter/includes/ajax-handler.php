<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apaf_filter_imoveis() {
	// Verify Nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'apaf_filter_nonce' ) ) {
		wp_send_json_error( 'Nonce verification failed' );
	}

	$args = array(
		'post_type'      => 'imovel',
		'posts_per_page' => -1, // Or set a limit
		'post_status'    => 'publish',
		'tax_query'      => array( 'relation' => 'AND' ),
		'meta_query'     => array( 'relation' => 'AND' ),
	);

	// Taxonomy: Cidade
	if ( ! empty( $_POST['cidade'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'cidade',
			'field'    => 'slug',
			'terms'    => sanitize_text_field( $_POST['cidade'] ),
		);
	}

	// Taxonomy: Bairro
	if ( ! empty( $_POST['bairro'] ) && is_array( $_POST['bairro'] ) ) {
		$bairros = array_map( 'sanitize_text_field', $_POST['bairro'] );
		$args['tax_query'][] = array(
			'taxonomy' => 'bairro',
			'field'    => 'slug',
			'terms'    => $bairros,
			'operator' => 'IN',
		);
	}

	// Taxonomy: Tipo Imovel
	if ( ! empty( $_POST['tipo_imovel'] ) && is_array( $_POST['tipo_imovel'] ) ) {
		$tipos = array_map( 'sanitize_text_field', $_POST['tipo_imovel'] );
		$args['tax_query'][] = array(
			'taxonomy' => 'tipo_imovel',
			'field'    => 'slug',
			'terms'    => $tipos,
			'operator' => 'IN',
		);
	}

	// Meta Query: Price
	$min_price = isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : 0;
	$max_price = isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : 9999999999;

	// Only add if max price is less than a huge number or min price is > 0
	if ( $min_price > 0 || $max_price < 9999999999 ) {
		$args['meta_query'][] = array(
			'key'     => 'preco_venda',
			'value'   => array( $min_price, $max_price ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	}

	// Meta Query: Quartos
	if ( isset( $_POST['quartos'] ) && $_POST['quartos'] !== '' ) {
		$quartos = sanitize_text_field( $_POST['quartos'] );
		if ( strpos( $quartos, '+' ) !== false ) {
			// Handle 4+
			$val = intval( $quartos );
			$args['meta_query'][] = array(
				'key'     => 'quartos',
				'value'   => $val,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		} else {
			$val = intval( $quartos );
			// Exact match or also >=? Prompt says "0", "1", "2", "3", "4+"
			// Usually filters like this imply "at least X" or exact match.
			// Re-reading: "These filter custom fields named 'quartos', 'banheiros', and 'vagas'".
			// "using >= comparison" is explicitly mentioned in prompt requirement 4.
			$args['meta_query'][] = array(
				'key'     => 'quartos',
				'value'   => $val,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}
	}

	// Meta Query: Banheiros
	if ( isset( $_POST['banheiros'] ) && $_POST['banheiros'] !== '' ) {
		$banheiros = sanitize_text_field( $_POST['banheiros'] );
		$val = intval( $banheiros );
		$args['meta_query'][] = array(
			'key'     => 'banheiros',
			'value'   => $val,
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	// Meta Query: Vagas
	if ( isset( $_POST['vagas'] ) && $_POST['vagas'] !== '' ) {
		$vagas = sanitize_text_field( $_POST['vagas'] );
		$val = intval( $vagas );
		$args['meta_query'][] = array(
			'key'     => 'vagas',
			'value'   => $val,
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	// Meta Query: Financiamento
	if ( ! empty( $_POST['aceita_financiamento'] ) ) {
		$args['meta_query'][] = array(
			'key'     => 'aceita_financiamento',
			'value'   => '1', // Assuming true/1 is stored
			'compare' => '=', // Or check if using Pods boolean field, might be 1/0
		);
	}

	$query = new WP_Query( $args );

	ob_start();

	if ( $query->have_posts() ) {
		echo '<div class="apaf-results-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			// Placeholder HTML card
			?>
			<div class="apaf-card">
				<div class="apaf-card-image">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'medium' ); ?>
					<?php else : ?>
						<div class="apaf-placeholder-image">Sem imagem</div>
					<?php endif; ?>
				</div>
				<div class="apaf-card-content">
					<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
					<div class="apaf-card-meta">
						<p>Preço: R$ <?php echo number_format( (float) get_post_meta( get_the_ID(), 'preco_venda', true ), 2, ',', '.' ); ?></p>
						<p>Quartos: <?php echo esc_html( get_post_meta( get_the_ID(), 'quartos', true ) ); ?></p>
						<p>Banheiros: <?php echo esc_html( get_post_meta( get_the_ID(), 'banheiros', true ) ); ?></p>
						<p>Vagas: <?php echo esc_html( get_post_meta( get_the_ID(), 'vagas', true ) ); ?></p>
					</div>
				</div>
			</div>
			<?php
		}
		echo '</div>';
		wp_reset_postdata();
	} else {
		echo '<p>Nenhum imóvel encontrado com os filtros selecionados.</p>';
	}

	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}

add_action( 'wp_ajax_filter_imoveis', 'apaf_filter_imoveis' );
add_action( 'wp_ajax_nopriv_filter_imoveis', 'apaf_filter_imoveis' );
