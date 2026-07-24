<?php
/**
 * Elementor widget subclasses. Loaded LAZILY from BLR_Elementor::register() only
 * when Elementor is active, because they extend \Elementor\Widget_Base which is
 * unavailable at plugins_loaded. Both widgets are thin — they delegate to the
 * shortcode renderer so there is exactly one implementation of the tool.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

if ( ! class_exists( 'BLR_Elementor_Router_Widget' ) ) {

	class BLR_Elementor_Router_Widget extends \Elementor\Widget_Base {
		public function get_name() { return 'braven_lead_router'; }
		public function get_title() { return 'Braven Lead Router'; }
		public function get_icon() { return 'eicon-form-horizontal'; }
		public function get_categories() { return array( 'general' ); }
		public function get_keywords() { return array( 'braven', 'lead', 'router', 'form', 'cta' ); }

		protected function register_controls() {
			$this->start_controls_section( 'content', array( 'label' => 'Content' ) );
			$this->add_control( 'heading', array(
				'label'   => 'Heading',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Find your program path',
			) );
			$this->add_control( 'intro', array(
				'label'   => 'Intro',
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => 'Answer three quick questions and we’ll route you to the right next step.',
			) );
			$this->end_controls_section();
		}

		protected function render() {
			$s = $this->get_settings_for_display();
			echo BLR_Shortcode::render_router( array( // phpcs:ignore WordPress.Security.EscapeOutput -- render_router returns escaped template markup.
				'heading' => $s['heading'] ?? '',
				'intro'   => $s['intro'] ?? '',
			) );
		}
	}

	class BLR_Elementor_Video_Widget extends \Elementor\Widget_Base {
		public function get_name() { return 'braven_video_library'; }
		public function get_title() { return 'Braven Video Library'; }
		public function get_icon() { return 'eicon-video-playlist'; }
		public function get_categories() { return array( 'general' ); }

		protected function register_controls() {
			$this->start_controls_section( 'content', array( 'label' => 'Content' ) );
			$this->add_control( 'heading', array(
				'label'   => 'Heading',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Training Library',
			) );
			$this->add_control( 'per_page', array(
				'label'   => 'Videos to show',
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 24,
			) );
			$this->end_controls_section();
		}

		protected function render() {
			$s = $this->get_settings_for_display();
			echo BLR_Shortcode::render_video_library( array( // phpcs:ignore WordPress.Security.EscapeOutput -- returns escaped template markup.
				'heading'  => $s['heading'] ?? 'Training Library',
				'per_page' => (int) ( $s['per_page'] ?? 24 ),
			) );
		}
	}
}
