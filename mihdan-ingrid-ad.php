<?php
/**
 * Plugin Name: Mihdan: Ingrid AD
 * Description: Встраивание рекламных постов ссылок в сетку постов на архивных страницах
 * Version: 1.0
 */

namespace Mihdan_Ingrid_AD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Core {

	/**
	 * Тип поста для хранения рекламных записей
	 */
	const POST_TYPE = 'mihdan_ingrid_ad';

	/**
	 * Ключ в мете для хранения ссылки
	 */
	const META_KEY = '_mihdan_ingrid_ad_post_source';

	/**
	 * Instance
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @static
	 *
	 * @var Core The single instance of the class.
	 */
	private static $_instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @static
	 *
	 * @return Core An instance of the class.
	 */
	public static function get_instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;

	}

	/**
	 * Constructor
	 *
	 * @since 1.0
	 *
	 * @access public
	 */
	public function __construct() {
		//$this->setup();
		$this->init();
	}

	public function init() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_metabox' ) );
		add_filter( 'the_posts', array( $this, 'the_posts' ), 10, 2 );
		add_filter( 'post_class', array( $this, 'post_class' ), 10, 3 );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 2 );
	}

	public function post_type_link( $post_link, \WP_Post $post ) {

		if ( self::POST_TYPE === get_post_type( $post->ID ) ) {
			$post_link = get_post_meta( $post->ID, '_mihdan_ingrid_ad_post_source', true );
		}

		return $post_link;
	}

	/**
	 * Добавить стандартный класс .post для нашего CPT
	 *
	 * @param $classes
	 * @param $class
	 * @param $post_id
	 *
	 * @return array
	 */
	public function post_class( $classes, $class, $post_id ) {

		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			$classes[] = 'post';
		}

		return $classes;
	}

	public function the_posts( $posts, \WP_Query $wp_query ) {

		if ( ! $wp_query->is_admin && $wp_query->is_main_query() ) {

			//echo '<!-- zalupa';

			// Получим случайный рекламный пост
			$args = array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'rand',
				'no_found_rows'  => true,
			);

			$query = new \WP_Query( $args );

			if ( $query->have_posts() ) {
				//while ( $query->have_posts() ) {
				//	$query->the_post();
				//
				//}
				$posts[] = $query->posts[0];
			}

			//print_r( $posts );
			//echo '-->';

			wp_reset_postdata();
		}

		return $posts;
	}

	public function register_cpt() {
		$labels = array(
			'name'          => 'Вопросы',
			'singular_name' => 'Вопрос',
			'menu_name'     => 'Архив вопросов',
			'all_items'     => 'Все вопросы',
			'add_new'       => 'Добавить вопрос',
			'add_new_item'  => 'Добавить новый вопрос',
			'edit'          => 'Редактировать',
			'edit_item'     => 'Редактировать вопрос',
			'new_item'      => 'Новый вопрос',
		);

		$args = array(
			'label'               => 'Ingrid AD',
			//'labels'              => $labels,
			'description'         => '',
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_rest'        => false,
			'rest_base'           => '',
			'show_in_menu'        => true,
			'exclude_from_search' => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'query_var'           => true,
			'supports'            => array( 'title', 'excerpt', 'thumbnail' ),
			'menu_icon'           => 'dashicons-layout',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	public function add_metabox( $post_type, \WP_Post $post ) {
		$screens = array( self::POST_TYPE );
		$content = function ( \WP_Post $post ) {
			$url = get_post_meta( $post->ID, '_mihdan_ingrid_ad_post_source', true );
			?>
			<table class="form-table">
				<tbody>
				<tr>
					<th>
						<label for="_mihdan_ingrid_ad_post_source">Ссылка на источник:</label>
					</th>
					<td>
						<input type="text" name="_mihdan_ingrid_ad_post_source" id="_mihdan_ingrid_ad_post_source" value="<?php echo esc_url( $url ); ?>" class="regular-text"/>
					</td>
				</tr>
				</tbody>
			</table>
			<?php
		};
		add_meta_box( 'post_additional', 'Дополнительно', $content, $screens, 'advanced', 'high' );
	}

	public function save_metabox( $post_id ) {

		// Убедимся что поле установлено.
		if ( ! isset( $_POST['_mihdan_ingrid_ad_post_source'] ) ) {
			return;
		}

		// если это автосохранение ничего не делаем
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// проверяем права юзера
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Очищаем значение поля input.
		$data = esc_url_raw( $_POST['_mihdan_ingrid_ad_post_source'] );

		// Обновляем данные в базе данных.
		update_post_meta( $post_id, '_mihdan_ingrid_ad_post_source', $data );
	}


}

/**
 * Хелпре для инициализации плагина
 *
 * @return Core
 */
function mihdan_ingrid_ad() {
	return Core::get_instance();
}

add_action( 'after_setup_theme', 'Mihdan_Ingrid_AD\mihdan_ingrid_ad' );

// eof;