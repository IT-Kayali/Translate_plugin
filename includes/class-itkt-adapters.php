<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

interface ITKT_Adapter_Interface {
    public function id();
    public function label();
    public function is_available();
    public function boot();
}

class ITKT_Adapters {
    private static $instance = null;
    private $adapters = array();

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        /**
         * Fires when third-party integrations may register an adapter.
         *
         * Example:
         * add_action( 'itkt_register_adapters', function( $registry ) {
         *     $registry->register( new My_ITKT_Adapter() );
         * } );
         */
        do_action( 'itkt_register_adapters', $this );
    }

    public function register( ITKT_Adapter_Interface $adapter ) {
        $this->adapters[ sanitize_key( $adapter->id() ) ] = $adapter;
        return $this;
    }

    public function all() {
        return $this->adapters;
    }

    public function available() {
        return array_filter( $this->adapters, function( $adapter ) {
            return $adapter->is_available();
        } );
    }

    public function boot_available() {
        foreach ( $this->available() as $adapter ) {
            $adapter->boot();
        }
    }
}
