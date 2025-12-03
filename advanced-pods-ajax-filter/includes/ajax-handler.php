<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Filter Logic
 */
function apaf_filter_imoveis() {
	// Verify Nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'apaf_filter_nonce' ) ) {
		wp_send_json_error( 'Nonce verification failed' );
	}

	$args = array(
		'post_type'      => 'imovel',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'tax_query'      => array( 'relation' => 'AND' ),
		'meta_query'     => array( 'relation' => 'AND' ),
	);

	// Taxonomy: Finalidade (Buy/Rent)
	// Assuming 'finalidade' taxonomy exists with 'venda' and 'locacao' terms.
	if ( ! empty( $_POST['finalidade'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'finalidade',
			'field'    => 'slug',
			'terms'    => sanitize_text_field( $_POST['finalidade'] ),
		);
	}

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

	// Taxonomy: Tipo Imovel (now single select in bar, but can handle array)
	if ( ! empty( $_POST['tipo_imovel'] ) ) {
		$tipos = $_POST['tipo_imovel'];
		if ( is_array( $tipos ) ) {
			$tipos = array_map( 'sanitize_text_field', $tipos );
		} else {
			$tipos = sanitize_text_field( $tipos );
		}

		$args['tax_query'][] = array(
			'taxonomy' => 'tipo_imovel',
			'field'    => 'slug',
			'terms'    => $tipos,
		);
	}

	// Taxonomy: Zona
	if ( ! empty( $_POST['zona'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'zona',
			'field'    => 'slug',
			'terms'    => sanitize_text_field( $_POST['zona'] ),
		);
	}

	// Meta Query: Price
	$min_price = isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : 0;
	$max_price = isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : 9999999999;

	if ( $min_price > 0 || $max_price < 9999999999 ) {
		$args['meta_query'][] = array(
			'key'     => 'preco_venda', // Might need to change if 'Rent' is selected
			'value'   => array( $min_price, $max_price ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	}

	// Meta Query: Quartos
	if ( isset( $_POST['quartos'] ) && $_POST['quartos'] !== '' ) {
		$quartos = sanitize_text_field( $_POST['quartos'] );
		if ( strpos( $quartos, '+' ) !== false ) {
			$val = intval( $quartos );
			$args['meta_query'][] = array(
				'key'     => 'quartos',
				'value'   => $val,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		} else {
			$val = intval( $quartos );
			// Exact match or >=? Prompt asked for numeric buttons.
			// Usually "3" means "3 bedrooms", not "3+". But often in UX "4+" is the only range.
			// I'll stick to exact match for 1, 2, 3 unless it's 4+.
			// Wait, the previous code used >= for all. Let's stick to >= as it's safer for "at least".
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
		$val = intval( $_POST['banheiros'] );
		$args['meta_query'][] = array(
			'key'     => 'banheiros',
			'value'   => $val,
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	// Meta Query: Vagas
	if ( isset( $_POST['vagas'] ) && $_POST['vagas'] !== '' ) {
		$val = intval( $_POST['vagas'] );
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
			'value'   => '1',
			'compare' => '=',
		);
	}

	$query = new WP_Query( $args );

	ob_start();

	if ( $query->have_posts() ) {
		echo '<div class="apaf-results-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
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


/**
 * Get Bairros by City
 */
function apaf_get_bairros() {
	if ( ! isset( $_GET['cidade'] ) ) {
		wp_send_json_error( 'Cidade not specified' );
	}

	$cidade_slug = sanitize_text_field( $_GET['cidade'] );

	// Strategy:
	// 1. Find all posts that are in this city.
	// 2. Get the 'bairro' terms associated with these posts.
	// 3. Return unique terms.

	// Query to get post IDs in the city
	$posts = get_posts( array(
		'post_type'      => 'imovel',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'tax_query'      => array(
			array(
				'taxonomy' => 'cidade',
				'field'    => 'slug',
				'terms'    => $cidade_slug,
			),
		),
	) );

	if ( empty( $posts ) ) {
		wp_send_json_success( array() ); // No posts, so no neighborhoods
	}

	// Get terms for these posts
	$terms = wp_get_object_terms( $posts, 'bairro' );

	if ( is_wp_error( $terms ) ) {
		wp_send_json_error( $terms->get_error_message() );
	}

	// Format for Select2
	$results = array();
	$unique_slugs = array();

	foreach ( $terms as $term ) {
		if ( ! in_array( $term->slug, $unique_slugs ) ) {
			$results[] = array(
				'id'   => $term->slug,
				'text' => $term->name
			);
			$unique_slugs[] = $term->slug;
		}
	}

	wp_send_json_success( $results );
}
// Hooked in main file
