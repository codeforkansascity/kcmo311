<?php
/**
    Process data from origial dataset in SODA2, ie not from a view.
*/
class Rows {

	private $data = array();
	private $column_names = array();

	private $max_size = array();

	private $offset=0;
	
	function __construct( $data ) {
		$this->data = $data;
		$this->number_of_rows = count( $this->data );


	}

	function next() {

		if ( $this->offset >= $this->number_of_rows ) {
			return false;
		}

		$row = array();
		$data = $this->data[$this->offset++];
		foreach ( $data as $i => $value ) {
			$row[ $i ] = $value;
		}
		$this->clean_row( $row );
		$this->set_max_size ( $row );
		return $row;
	}

	function clean_row( &$row ) {


	}

	function set_max_size( $row ) {

		foreach ( $row AS $k => $v ) {
            if ( is_string( $v )) {
    			$l = strlen( $v );

    			if ( array_key_exists( $k, $this->max_size )) {
    				if ( $l > $this->max_size[ $k ] ) {
    					$this->max_size[ $k ] = $l;
    				}
    			} else {
    				$this->max_size[ $k ] = $l;
    			}
		    }
		}
	}

	function display_max_size() {

		foreach ( $this->max_size AS $k => $v ) {
			print "`$k` = $v\n";

		}
	}
}
