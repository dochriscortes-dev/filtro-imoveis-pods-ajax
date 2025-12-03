<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handler to get Neighborhoods based on City
 */
function apaf_get_bairros() {
	// Verify Nonce
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'apaf_filter_nonce' ) ) {
		wp_send_json_error( 'Nonce verification failed' );
	}

	if ( empty( $_GET['cidade_slug'] ) ) {
		wp_send_json_error( 'City slug missing' );
	}

	$cidade_slug = sanitize_text_field( $_GET['cidade_slug'] );

	// Query posts in this city to find relevant neighborhoods
	// This is the most accurate way if there is no direct parent-child relationship between taxonomies
	$args = array(
		'post_type'      => 'imovel',
		'posts_per_page' => -1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'cidade',
				'field'    => 'slug',
				'terms'    => $cidade_slug,
			),
		),
		'fields' => 'ids', // Only get IDs to be faster
	);

	$posts_in_city = get_posts( $args );

	if ( empty( $posts_in_city ) ) {
		wp_send_json_success( array() ); // No posts, so no neighborhoods
	}

	// Get terms of 'bairro' taxonomy assigned to these posts
	$bairros = wp_get_object_terms( $posts_in_city, 'bairro', array( 'fields' => 'all' ) );

	if ( is_wp_error( $bairros ) ) {
		wp_send_json_error( 'Error retrieving neighborhoods' );
	}

	// Format for Select2
	$formatted_bairros = array();
	$seen_slugs = array();

	foreach ( $bairros as $bairro ) {
		if ( ! in_array( $bairro->slug, $seen_slugs ) ) {
			$formatted_bairros[] = array(
				'id'   => $bairro->slug,
				'text' => $bairro->name,
			);
			$seen_slugs[] = $bairro->slug;
		}
	}

	wp_send_json_success( $formatted_bairros );
}
add_action( 'wp_ajax_apaf_get_bairros', 'apaf_get_bairros' );
add_action( 'wp_ajax_nopriv_apaf_get_bairros', 'apaf_get_bairros' );


/**
 * AJAX Handler to Filter Properties
 */
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

	// Taxonomy: Finalidade (Operation)
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
	if ( ! empty( $_POST['bairro'] ) ) {
		// Can be array or string
		$bairros = is_array( $_POST['bairro'] ) ? $_POST['bairro'] : array( $_POST['bairro'] );
		$bairros = array_map( 'sanitize_text_field', $bairros );
		$args['tax_query'][] = array(
			'taxonomy' => 'bairro',
			'field'    => 'slug',
			'terms'    => $bairros,
			'operator' => 'IN',
		);
	}

	// Taxonomy: Tipo Imovel
	if ( ! empty( $_POST['tipo_imovel'] ) ) {
		// Can be array or string
		$tipos = is_array( $_POST['tipo_imovel'] ) ? $_POST['tipo_imovel'] : array( $_POST['tipo_imovel'] );
		$tipos = array_map( 'sanitize_text_field', $tipos );
		$args['tax_query'][] = array(
			'taxonomy' => 'tipo_imovel',
			'field'    => 'slug',
			'terms'    => $tipos,
			'operator' => 'IN',
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
	$max_price = isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : 50000000; // 50M default max

	// Only add if range is specific (not full default range 0-50M if that's the bounds)
	// But usually safer to just always add if passed.
	// Range: 20k to 50M
	$args['meta_query'][] = array(
		'key'     => 'preco_venda',
		'value'   => array( $min_price, $max_price ),
		'type'    => 'NUMERIC',
		'compare' => 'BETWEEN',
	);

	// Helper for numeric >= fields
	$numeric_fields = array( 'quartos', 'banheiros', 'vagas' );
	foreach ( $numeric_fields as $field ) {
		if ( isset( $_POST[ $field ] ) && $_POST[ $field ] !== '' ) {
			$val = sanitize_text_field( $_POST[ $field ] );
			// If it has '+', remove it, it's still >= logic
			$val = intval( str_replace( '+', '', $val ) );

			// Only add query if value > 0, assuming 0 means "any" or "don't care" in some contexts,
			// BUT user has buttons for 0, 1, 2, 3, 4+.
			// If user selects 0, they might mean "exactly 0" or "0 or more" (which is everything).
			// However, usually these filters are "at least".
			// But for "0", "at least 0" is everything.
			// If the user actively selects "0", maybe they want properties with 0 bedrooms (studio/land)?
			// The prompt says "Buttons for ... (0, 1, 2, 3, 4+)".
			// If I click '2', I expect 2 or more.
			// If I click '0', I expect 0 or more (all). So filtering by 0 is redundant unless we want EXACT match.
			// But usually these are "Min Bedrooms".
			// I'll stick to >= $val.

			$args['meta_query'][] = array(
				'key'     => $field,
				'value'   => $val,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}
	}

	// Meta Query: Financiamento
	if ( ! empty( $_POST['aceita_financiamento'] ) ) {
		$args['meta_query'][] = array(
			'key'     => 'aceita_financiamento',
			'value'   => '1',
			'compare' => '=', // Assumes 1 is stored for yes
		);
	}

	$query = new WP_Query( $args );

	ob_start();

	if ( $query->have_posts() ) {
		echo '<div class="apaf-results-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();

			$preco = get_post_meta( get_the_ID(), 'preco_venda', true );
			$quartos = get_post_meta( get_the_ID(), 'quartos', true );
			$banheiros = get_post_meta( get_the_ID(), 'banheiros', true );
			$vagas = get_post_meta( get_the_ID(), 'vagas', true );
			$area = get_post_meta( get_the_ID(), 'area', true ); // Added area just in case

			?>
			<div class="apaf-card">
				<div class="apaf-card-image">
					<a href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium_large' ); ?>
						<?php else : ?>
							<span class="apaf-no-image">Ver Detalhes</span>
						<?php endif; ?>
					</a>
				</div>
				<div class="apaf-card-content">
					<h3 class="apaf-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<div class="apaf-card-price">
						<?php echo $preco ? 'R$ ' . number_format( (float)$preco, 2, ',', '.' ) : 'Consulte'; ?>
					</div>
					<div class="apaf-card-features">
						<?php if ( $quartos !== '' ) : ?><span><i class="fa fa-bed"></i> <?php echo esc_html( $quartos ); ?></span><?php endif; ?>
						<?php if ( $banheiros !== '' ) : ?><span><i class="fa fa-bath"></i> <?php echo esc_html( $banheiros ); ?></span><?php endif; ?>
						<?php if ( $vagas !== '' ) : ?><span><i class="fa fa-car"></i> <?php echo esc_html( $vagas ); ?></span><?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		}
		echo '</div>';
		wp_reset_postdata();
	} else {
		echo '<div class="apaf-no-results">Nenhum imóvel encontrado com os filtros selecionados.</div>';
	}

	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}

add_action( 'wp_ajax_apaf_filter_imoveis', 'apaf_filter_imoveis' );
add_action( 'wp_ajax_nopriv_apaf_filter_imoveis', 'apaf_filter_imoveis' );
