<?php

class WpCmTable extends WP_List_Table {

	public function __construct( $args = array() ) {
		parent::__construct( $args );
	}

	public function set_data( $data ) {
		if ( is_array( $data ) && count( $data ) > 0 ) {
			if ( is_object( $data[0] ) ) {
				for ( $i = 0; $i < count( $data ); $i ++ ) {
					$data[ $i ] = (array) $data[ $i ];
				}
			}
		}
		$this->items = $data;
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
			case 'path':
				return $item[ $column_name ];
			case 'radio':
				return $this->radio_from_item( $item );

			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

	public function radio_from_item( $item ) {
		$val = $item['path'];

		return "<input type='radio' name='project_to_check' value='$val'>";
	}

	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

	}

	public function get_columns() {
		$columns = array(
			'radio' => "Select",
			'title' => 'Title',
			'path'  => 'Path'
		);

		return $columns;
	}

	public function column_title( $item ) {
		$page = $_REQUEST['page'];

		$q_view    = ci_url_action( 'view', array( 'project_to_check' => $item['path'] ) );
		$q_update  = ci_url_action( 'select-update', array( 'project_to_check' => $item['path'] ) );
		$q_require = ci_url_action( 'require', array( 'project_to_check' => $item['path'] ) );


		$actions = array(
			'view'    => ci_link( $q_view, 'View' ),
			'update'  => ci_link( $q_update, 'Update' ),
			'require' => ci_link( $q_require, 'Require' )
		);

		return $item['title'] . ' ' . $this->row_actions( $actions );

	}


}
