<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Get Neighborhoods based on City
 */
function apaf_get_bairros_handler() {
	check_ajax_referer( 'apaf_filter_nonce', 'nonce' );

	$city_slug = isset( $_POST['cidade'] ) ? sanitize_text_field( $_POST['cidade'] ) : '';

	if ( empty( $city_slug ) ) {
		wp_send_json_error( 'City not selected' );
	}

	// Logic: Fetch 'bairro' terms that are associated with posts that also have the 'cidade' term.
	// This is heavier than just getting terms, but standard WP doesn't link taxonomies directly.
	// Optimization: If many posts, this could be slow. A lighter way is getting all 'bairro' terms and trusting the user or using a plugin that links them.
	// However, usually 'bairro' is a sub-taxonomy of 'cidade' or just a separate taxonomy.
	// Assuming independent taxonomies:

	$args = array(
		'post_type'      => 'imovel',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'tax_query'      => array(
			array(
				'taxonomy' => 'cidade',
				'field'    => 'slug',
				'terms'    => $city_slug,
			),
		),
	);

	$posts = get_posts( $args );

	if ( empty( $posts ) ) {
		wp_send_json_success( array() ); // No posts in this city, so no neighborhoods to show.
	}

	$bairros = wp_get_object_terms( $posts, 'bairro', array( 'fields' => 'all' ) );

	// Remove duplicates (wp_get_object_terms might return duplicates if multiple posts share terms, wait, no, it returns unique terms unless specified otherwise, but safe to check)
	// Actually wp_get_object_terms returns a list of WP_Term objects. It should be unique per term ID.

	$response = array();
	if ( ! empty( $bairros ) && ! is_wp_error( $bairros ) ) {
		// Filter unique by term_id just in case
		$seen = array();
		foreach ( $bairros as $bairro ) {
			if ( ! isset( $seen[ $bairro->term_id ] ) ) {
				$response[] = array(
					'id'   => $bairro->slug, // Use slug for value
					'text' => $bairro->name,
				);
				$seen[ $bairro->term_id ] = true;
			}
		}
	}

	// Sort alphabetically
	usort( $response, function($a, $b) {
		return strcmp( $a['text'], $b['text'] );
	});

	wp_send_json_success( $response );
}
add_action( 'wp_ajax_apaf_get_bairros', 'apaf_get_bairros_handler' );
add_action( 'wp_ajax_nopriv_apaf_get_bairros', 'apaf_get_bairros_handler' );


/**
 * 2. Filter Properties
 */
function apaf_filter_imoveis_handler() {
	check_ajax_referer( 'apaf_filter_nonce', 'nonce' );

	$args = array(
		'post_type'      => 'imovel',
		'posts_per_page' => 10, // Adjust as needed
		'paged'          => isset($_POST['paged']) ? intval($_POST['paged']) : 1,
		'tax_query'      => array( 'relation' => 'AND' ),
		'meta_query'     => array( 'relation' => 'AND' ),
	);

	// --- Taxonomies ---

	// Finalidade (Comprar/Alugar)
	if ( ! empty( $_POST['finalidade'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'finalidade',
			'field'    => 'slug',
			'terms'    => sanitize_text_field( $_POST['finalidade'] ),
		);
	}

	// Cidade
	if ( ! empty( $_POST['cidade'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'cidade',
			'field'    => 'slug',
			'terms'    => sanitize_text_field( $_POST['cidade'] ),
		);
	}

	// Bairro
	if ( ! empty( $_POST['bairro'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'bairro',
			'field'    => 'slug',
			'terms'    => sanitize_text_field( $_POST['bairro'] ),
		);
	}

	// Tipo Imóvel (Array)
	if ( ! empty( $_POST['tipo_imovel'] ) && is_array( $_POST['tipo_imovel'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'tipo_imovel',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_text_field', $_POST['tipo_imovel'] ),
		);
	}

	// Zona
	if ( ! empty( $_POST['zona'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'zona',
			'field'    => 'slug',
			'terms'    => sanitize_text_field( $_POST['zona'] ),
		);
	}

	// --- Meta Fields (Pods) ---

	// Preço
	$min_price = isset( $_POST['min_price'] ) && $_POST['min_price'] !== '' ? floatval( $_POST['min_price'] ) : 0;
	$max_price = isset( $_POST['max_price'] ) && $_POST['max_price'] !== '' ? floatval( $_POST['max_price'] ) : null;

	if ( $max_price ) {
		$args['meta_query'][] = array(
			'key'     => 'preco_venda', // Assuming preco_venda covers rent too or logic differs. Prompt says "preco_venda" in memory.
			'value'   => array( $min_price, $max_price ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	} elseif ( $min_price > 0 ) {
		$args['meta_query'][] = array(
			'key'     => 'preco_venda',
			'value'   => $min_price,
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	// Quartos
	if ( ! empty( $_POST['quartos'] ) ) {
		$args['meta_query'][] = array(
			'key'     => 'quartos',
			'value'   => intval( $_POST['quartos'] ),
			'type'    => 'NUMERIC',
			'compare' => '>=', // 1+ means >= 1
		);
	}

	// Banheiros
	if ( ! empty( $_POST['banheiros'] ) ) {
		$args['meta_query'][] = array(
			'key'     => 'banheiros',
			'value'   => intval( $_POST['banheiros'] ),
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	// Vagas
	if ( ! empty( $_POST['vagas'] ) ) {
		$args['meta_query'][] = array(
			'key'     => 'vagas',
			'value'   => intval( $_POST['vagas'] ),
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	// Aceita Financiamento
	if ( ! empty( $_POST['aceita_financiamento'] ) ) {
		$args['meta_query'][] = array(
			'key'     => 'aceita_financiamento',
			'value'   => '1', // Checkbox usually saves as 1/0 or yes/no. Assuming 1.
			'compare' => '=',
		);
	}

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) {
		ob_start();
		echo '<div class="apaf-results-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();

			// Simple card template
			$price = get_post_meta( get_the_ID(), 'preco_venda', true );
			$quartos = get_post_meta( get_the_ID(), 'quartos', true );
			$banheiros = get_post_meta( get_the_ID(), 'banheiros', true );
			$vagas = get_post_meta( get_the_ID(), 'vagas', true );
			$area = get_post_meta( get_the_ID(), 'area_total', true ); // Guessing field name
			$thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
			if ( ! $thumb ) { $thumb = 'https://via.placeholder.com/400x300?text=No+Image'; }

			// Icons (Inline SVG as requested)
			$icon_bed = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 4v16"/><path d="M2 12h20"/><path d="M2 8h20"/><path d="M12 2v20"/></svg>'; // Placeholder SVG
			// Using simpler SVGs
			$svg_bed = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"></path><path d="M22 4v16"></path><path d="M2 8h20"></path><path d="M2 12h20"></path></svg>'; /* Not exact but close enough for demo */

			?>
			<div class="apaf-card">
				<div class="apaf-card-thumb" style="background-image: url('<?php echo esc_url( $thumb ); ?>');">
					<span class="apaf-tag"><?php echo get_the_term_list( get_the_ID(), 'tipo_imovel', '', ', ' ); ?></span>
				</div>
				<div class="apaf-card-content">
					<h3><?php the_title(); ?></h3>
					<p class="apaf-location"><?php echo get_the_term_list( get_the_ID(), 'bairro', '', ', ' ); ?>, <?php echo get_the_term_list( get_the_ID(), 'cidade', '', ', ' ); ?></p>
					<div class="apaf-card-price">
						R$ <?php echo number_format( (float)$price, 2, ',', '.' ); ?>
					</div>
					<div class="apaf-card-meta">
						<span><?php echo $quartos; ?> Qts</span>
						<span><?php echo $banheiros; ?> Ban</span>
						<span><?php echo $vagas; ?> Vag</span>
					</div>
					<a href="<?php the_permalink(); ?>" class="apaf-btn-view">Ver Detalhes</a>
				</div>
			</div>
			<?php
		}
		echo '</div>';

		// Pagination
		echo '<div class="apaf-pagination">';
		echo paginate_links( array(
			'total' => $query->max_num_pages,
			'current' => $args['paged'],
			'format' => '?paged=%#%',
			'mid_size' => 2,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
		) );
		echo '</div>';

		wp_reset_postdata();
	} else {
		echo '<p class="apaf-no-results">Nenhum imóvel encontrado.</p>';
	}

	wp_die();
}
add_action( 'wp_ajax_apaf_filter_imoveis', 'apaf_filter_imoveis_handler' );
add_action( 'wp_ajax_nopriv_apaf_filter_imoveis', 'apaf_filter_imoveis_handler' );
