<?php
/**
 * Plugin Name: Mihdan: Ingrid AD
 * Description: Встраивание рекламных постов ссылок в сетку постов на архивных страницах
 * Version: 1.0
 *
 * GitHub Plugin URI: https://github.com/mihdan/mihdan-ingrid-ad
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

	/**
	 * Инициализация хуков
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_metabox' ) );
		add_filter( 'the_posts', array( $this, 'the_posts' ), 10, 2 );
		add_filter( 'post_class', array( $this, 'post_class' ), 10, 3 );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 2 );
	}

	/**
	 * Подменяем ссылку на пост
	 *
	 * @param string   $post_link ссылка по умолчанию.
	 * @param \WP_Post $post объект текущего поста.
	 *
	 * @return string
	 */
	public function post_type_link( $post_link, \WP_Post $post ) {

		if ( self::POST_TYPE === get_post_type( $post->ID ) ) {
			$post_link = get_post_meta( $post->ID, '_mihdan_ingrid_ad_post_source', true );
		}

		return $post_link;
	}

	/**
	 * Добавляем стандартный класс .post для нашего CPT
	 *
	 * @param array   $classes массив классов по-умолчанию.
	 * @param array   $class
	 * @param integer $post_id идентификатор поста.
	 *
	 * @return array
	 */
	public function post_class( $classes, $class, $post_id ) {

		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			$classes[] = 'post';
		}

		return $classes;
	}

	/**
	 * Добавляем случайный рекламный пост в массив записей до их вывода
	 *
	 * @param array     $posts массив постов по умолчанию.
	 * @param \WP_Query $wp_query объект запроса
	 *
	 * @return array
	 */
	public function the_posts( $posts, \WP_Query $wp_query ) {

		if ( ! $wp_query->is_admin && $wp_query->is_main_query() && ( $wp_query->is_archive() || $wp_query->is_home() ) ) {

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
				/** @var \WP_Post $post */
				$_post = $query->posts[0];

				// Получить позицию рекламного поста
				$position = get_post_meta( $_post->ID, '_mihdan_ingrid_ad_post_position', true );

				// Ставим новый пост в нужную позицию
				array_splice( $posts, ( $position - 1 ), 0, $query->posts );
			}

			wp_reset_postdata();
		}

		return $posts;
	}

	/**
	 * Регистрируем свой CPT для хранения рекламных постов
	 */
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

	/**
	 * Добавляем метабокс с полями на страницу редактирования рекламного поста
	 *
	 * @param string   $post_type тип записи.
	 * @param \WP_Post $post объект поста.
	 */
	public function add_metabox( $post_type, \WP_Post $post ) {
		$screens = array( self::POST_TYPE );
		$content = function ( \WP_Post $post ) {
			$url      = get_post_meta( $post->ID, '_mihdan_ingrid_ad_post_source', true );
			$position = get_post_meta( $post->ID, '_mihdan_ingrid_ad_post_position', true );
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
				<tr>
					<th>
						<label for="_mihdan_ingrid_ad_post_position">Позиция на странице:</label>
					</th>
					<td>
						<input type="text" name="_mihdan_ingrid_ad_post_position" id="_mihdan_ingrid_ad_post_position" value="<?php echo absint( $position ); ?>" class="regular-text"/>
					</td>
				</tr>
				</tbody>
			</table>
			<?php
		};
		add_meta_box( 'post_additional', 'Дополнительно', $content, $screens, 'advanced', 'high' );
	}

	/**
	 * Обработчик сохранения данных метабокса
	 *
	 * @param integer $post_id идентификатор поста.
	 */
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
		$post_source   = esc_url_raw( $_POST['_mihdan_ingrid_ad_post_source'] );
		$post_position = absint( $_POST['_mihdan_ingrid_ad_post_position'] );

		// Обновляем данные в базе данных.
		update_post_meta( $post_id, '_mihdan_ingrid_ad_post_source', $post_source );
		update_post_meta( $post_id, '_mihdan_ingrid_ad_post_position', $post_position );
	}


}

/**
 * Хелпер для инициализации плагина
 *
 * @return Core
 */
function mihdan_ingrid_ad() {
	return Core::get_instance();
}

add_action( 'after_setup_theme', 'Mihdan_Ingrid_AD\mihdan_ingrid_ad' );

// eof;
