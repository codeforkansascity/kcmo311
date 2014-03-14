<?php
/**
    Process data from a view that is on data.kcmo.org.
*/
class Rows {

	private $data = array();
	private $column_names = array();

	private $max_size = array();

	private $offset=0;
	
	function __construct( $data ) {
		$this->data = $data;
		$this->make_column_map();
		$this->number_of_rows = count( $this->data['data'] );


	}

	function make_column_map () {
		$view = $this->data['meta']['view'];

		$socrata_columns = $view['columns'];
		foreach ( $socrata_columns AS $offset => $column ) {
			$this->column_names[$offset] = str_replace(':','',$column['fieldName']);
		}
	}

	function next() {

		if ( $this->offset >= $this->number_of_rows ) {
			return false;
		}

		$row = array();
		$data = $this->data['data'][$this->offset++];
		foreach ( $this->column_names as $i => $name ) {
			$row[ $name ] = $data[$i];
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
