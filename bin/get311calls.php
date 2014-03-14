<?php

/*
 Description: Get 311 calls from Socrata  https://data.kcmo.org/api/views/djv7-4q5r
 
*/

require_once("lib/socrata.php");

require_once("lib/socrata-get-row-original-dataset.php");

class CleanCalls extends Rows {

   var $fields = array( 
        'source' , 
        'department' , 
        'work_group' , 
        'request_type' , 
        'creation_date' , 
        'closed_date' , 
        'days_to_close' , 
        'status' , 
        'exceeded_est_timeframe' , 
        'zip_code' , 
        'neighborhood' , 
        'council_district' , 
        'parcel_id_no' , 
        'xcoordinate' , 
        'ycoordinate' , 
        'latitude' , 
        'longitude' , 
        'address_city' , 
        'address_state' , 
        'address_zip' , 
        'address_line_1' 
      );

    private $mysqli = null;

    function __construct( $mysqli, $data ) {

        $this->mysqli = $mysqli;

        parent::__construct( $data );

    }

    /**
     * Take data from Socrata and
     *  1) extract address from the address_with_geocode field along
     *     with latitude and longitude
     *  2) Replace values with forign keys for
     *     department
     *     work_group
     *     request_type
     *     neighborhood
     *
     */

    function clean_row( &$row ) {


        if ( array_key_exists( 'address_with_geocode', $row )) {
            $address = json_decode($row['address_with_geocode']['human_address']);

            if ( array_key_exists( 'latitude', $row['address_with_geocode'] )) {
                $row['latitude']  = $row['address_with_geocode']['latitude'];
            }
            if ( array_key_exists( 'longitude', $row['address_with_geocode'] )) {
                $row['longitude'] = $row['address_with_geocode']['longitude'];
            }

            if ( empty($address->city) && empty($address->state) && empty($address->zip) ) {

                $address->address = str_replace(' Kansas City, Missouri','',$address->address,$cnt);
                if ( $cnt ) {
                    $row['address_city']   = 'Kansas City';
                    $row['address_state']  = 'Missouri';
                }
                $tmp = $address->address;
                $zip = preg_replace('/(.*) (\d*)$/','$2',$tmp,$cnt);

                if ( $cnt ) {
                    $row['address_zip']    = $zip;
                    $address->address = str_replace(' ' + $zip,'',$address->address,$cnt);
                } else {
                    $row['address_zip']    = '';
            
                }
            } else {
                $row['address_city']   = $address->city;
                $row['address_state']  = $address->state;
                $row['address_zip']    = $address->zip;
            }
            $row['address_line_1'] = $address->address;
        }

        // Make sure we have values for all the field names except for the key
        foreach ( $this->fields AS $field_name ) {
          if ( !array_key_exists( $field_name, $row ) ) {
                $row[$field_name] = '';
          }
        }

        // Turn dates into MySQL dates it remove the 'T' between the date and time

        $row['creation_date'] = str_replace('T', ' ', $row['creation_date']);
        $row['closed_date'] = str_replace('T', ' ', $row['closed_date']);

        $row['department_id'] = $this->find( 'departments', $row['department']);
        unset( $row['department'] );

        $row['work_group_id'] = $this->find( 'work_groups', $row['work_group']);
        unset( $row['work_group'] );

        $row['request_type_id'] = $this->find( 'request_types', $row['request_type']);
        unset( $row['request_type'] );

        $row['neighborhood_id'] = $this->find( 'neighborhoods', $row['neighborhood']);
        unset( $row['neighborhood'] );

   }


    function find( $table, $value ) {
        $id = 9999;
        $str = $this->mysqli->escape_string( $value );
        if ($result = mysqli_query($this->mysqli, "SELECT * FROM $table WHERE name = '$str'")) {
            if ( $result->num_rows == 0 ) {
                $insert = $this->mysqli->prepare("INSERT INTO $table (name) VALUES (?)");
                $insert->bind_param('s',$value);
                
                if ( $insert->execute() ) {
                    $id = $insert->insert_id;
                } else {
                    $id = 0;
                }
                $insert->close();
            } else {
                $existing_row = $result->fetch_assoc();
                $id = $existing_row['id'];
            }
            mysqli_free_result($result);
        } else {
            $a = mysqli_error($mysqli);
            print_r($a);
            var_dump($result); die;
        }
       return $id;


    }


}


include './config.php';

class ProcessCalls {

    private $mysqli = null;

    private $max_loops = 1;
    private $loop_cnt = 0;
    private $order = 'case_id desc';
    private $limit = 5;
    private $offset = 0;

    function __construct( $limit=5, $offset=0, $max_loops=1, $order='case_id desc' ) {

        $this->limit = $limit;
        $this->offset = $offset;
        $this->max_loops = $max_loops;
        $this->order = $order;


        global $DB_NAME;
        global $DB_USER;
        global $DB_PASS;
        global $DB_HOST;

		$this->mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
		
		/* check connection */
		if (mysqli_connect_errno()) {
		    printf("Connect failed: %s\n", mysqli_connect_error());
		    exit();
		}

        $this->insert_statement = $this->create_insert_statement();
        $this->update_statement = $this->create_update_statement();

    }		

    function get_calls() {
	
		$socrata = new Socrata("http://data.kcmo.org");

		
		
		$add_count = 0;
		$update_count = 0;
		$same_count = 0;
		$total_count = 0;

		//  looking at http://dev.socrata.com/docs/queries.
		 $data = $socrata->get('/resource/7at3-sxhp.json',array( '$order' => $this->order, '$offset' => $this->offset, '$limit' => $this->limit ));  
		//$data = $socrata->get('/resource/7at3-sxhp.json',array( '$where' => 'case_id=2013140946' ));  

        $insert_stmt = $this->create_insert_statement();
        $update_stmt = $this->create_update_statement();
		
		while ( !empty( $data ) && $this->loop_cnt < $this->max_loops ) {
		
		    print "LOOP=$this->loop_cnt\n\n";
		    $rows = new CleanCalls( $this->mysqli, $data );
			while ($row = $rows->next()) {
		
		        $total_count++;   
				$case_id = $row['case_id'];
				$closed_date = $row['closed_date'];
			    print "$case_id, $closed_date, ";
		
		        if ($result = mysqli_query($this->mysqli, "SELECT * FROM three11_calls WHERE case_id = $case_id")) {
		            if ( $result->num_rows == 0 ) {
		                $this->add_row( $row, $insert_stmt  );
		                $add_count++;
		               print "add\n";
		            } else {
		                $existing_row = $result->fetch_assoc();
		                unset($row['street_address']);
		                unset($row['address_with_geocode']);
		                unset($row['creation_month']);
		                unset($row['creation_year']);
		                unset($row['closed_month']);
		                unset($row['closed_year']);
		                $diff_row = array_diff( $row, $existing_row );
		                if ( !empty( $diff_row )) {
		                    $this->update_call( $update_stmt, $case_id, $row, $diff_row );
		                    $update_count++;
		                    print "update\n";
		                } else {
		                    $same_count++;
		                    print "same\n";
		
		                }
		            }
		            mysqli_free_result($result);
		        }
		    }

		    $this->loop_cnt++;
		    $this->offset += $this->limit;
		    $data = $socrata->get('/resource/7at3-sxhp.json',array( '$order' => $this->order, '$offset' => $this->offset, '$limit' => $this->limit ));  
		
		}

	    print "\n\nLOOP=$this->loop_cnt\n";
    	print "records added $add_count\n";
    	print "records changed $update_count\n";
    	print "records same $same_count\n";
    	print "total $total_count\n";
    }

	
	function add_row( &$row, &$insert_stmt ) {
		if (!($insert_stmt->bind_param('ssssssssssssssssssssss',
		    $case_id,
		    $source,
		    $department_id,
		    $work_group_id,
		    $request_type_id,
		    $creation_date,
		    $closed_date,
		    $days_to_close,
		    $status,
		    $exceeded_est_timeframe,
		    $zip_code,
		    $neighborhood_id,
		    $council_district,
		    $parcel_id_no,
		    $xcoordinate,
		    $ycoordinate,
		    $latitude,
		    $longitude,
		    $address_city,
		    $address_state,
		    $address_zip,
		    $address_line_1))) {
		    echo "Binding parameters failed: (" . $insert_stmt->errno . ") " . $insert_stmt->error;
		die;
		}
		
			$case_id = $row['case_id'];
			$source = $row['source'];
			$department_id = $row['department_id'];
			$work_group_id = $row['work_group_id'];
			$request_type_id = $row['request_type_id'];
			$creation_date = $row['creation_date'];
			$closed_date = array_key_exists('close_date',$row) ? $row['closed_date'] : '0000-00-00';
			$days_to_close = array_key_exists('days_to_close',$row) ? $row['days_to_close'] : '0';
			$status = $row['status'];
			$exceeded_est_timeframe = $row['exceeded_est_timeframe'];
			$zip_code = $row['zip_code'];
			$neighborhood_id = array_key_exists('neighborhood_id',$row) ? $row['neighborhood_id'] : '0';
			$council_district = array_key_exists('council_district',$row) ? $row['council_district'] : '0';
			$parcel_id_no = $row['parcel_id_no'];
			$xcoordinate = $row['xcoordinate'];
			$ycoordinate = $row['ycoordinate'];
			$latitude = $row['latitude'];
			$longitude = $row['longitude'];
			$address_city = $row['address_city'];
			$address_state = $row['address_state'];
			$address_zip = $row['address_zip'];
			$address_line_1 = $row['address_line_1'];
			
			
			/* execute prepared statement */
			if (!($insert_stmt->execute())) {
		        echo "Execute failed: (" . $insert_stmt->errno . ") " . $insert_stmt->error;
	            die;
		    }
			
		
		        
		// $rows->display_max_size();
		
	
	}

	
	function update_call( &$update_stmt, $case_id, $row, $diff_row ) {
	
	
	    foreach ( $diff_row AS $field => $value ) {
	        $row[$field] = $value;
	    }
	
		if (!($update_stmt->bind_param('ssssssssssssssssssssss',
		    $row['source'],
		    $row['department_id'],
		    $row['work_group_id'],
		    $row['request_type_id'],
		    $row['creation_date'],
		    $row['closed_date'],
		    $row['days_to_close'],
		    $row['status'],
		    $row['exceeded_est_timeframe'],
		    $row['zip_code'],
		    $row['neighborhood_id'],
		    $row['council_district'],
		    $row['parcel_id_no'],
		    $row['xcoordinate'],
		    $row['ycoordinate'],
		    $row['latitude'],
		    $row['longitude'],
		    $row['address_city'],
		    $row['address_state'],
		    $row['address_zip'],
		    $row['address_line_1'],
		    $row['case_id']
    	))) {
		    echo "Binding parameters failed: (" . $update_stmt->errno . ") " . $update_stmt->error;
	    	die;
		}
	        /* execute prepared statement */
	        if (!($update_stmt->execute())) {
	            echo "Execute failed: (" . $update_stmt->errno . ") " . $update_stmt->error;
	            die;
	        }
	
	}


    function create_insert_statement () {
		if (!($insert_stmt = $this->mysqli->prepare(
            "INSERT INTO three11_calls ( 
	                `case_id` , 
	                `source` , 
	                `department_id` , 
	                `work_group_id` , 
	                `request_type_id` , 
	                `creation_date` , 
	                `closed_date` , 
	                `days_to_close` , 
	                `status` , 
	                `exceeded_est_timeframe` , 
	                `zip_code` , 
	                `neighborhood_id` , 
	                `council_district` , 
	                `parcel_id_no` , 
	                `xcoordinate` , 
	                `ycoordinate` , 
	                `latitude` , 
	                `longitude` , 
	                `address_city` , 
	                `address_state` , 
	                `address_zip` , 
	                `address_line_1`  
	    		) VALUES ( 
	                ?, ?, ?, ?, ?,
	                ?, ?, ?, ?, ?,
	                ?, ?, ?, ?, ?,
	                ?, ?, ?, ?, ?,
	                ?, ? 
	            )"
            ) 
        )) {
        		echo "Prepare failed: (" . $this->mysqli->errno . ") " . $this->mysqli->error;
                die;
		}
        return $insert_stmt;
    }

    function create_update_statement() {
		
		if (!($update_stmt = $this->mysqli->prepare(
            "UPDATE three11_calls  SET
			    `source` = ?, 
			    `department_id` = ?, 
			    `work_group_id` = ?, 
			    `request_type_id` = ?, 
			    `creation_date` = ?, 
			    `closed_date` = ?, 
			    `days_to_close` = ?, 
			    `status` = ?, 
			    `exceeded_est_timeframe` = ?, 
			    `zip_code` = ?, 
			    `neighborhood_id` = ?, 
			    `council_district` = ?, 
			    `parcel_id_no` = ?, 
			    `xcoordinate` = ?, 
			    `ycoordinate` = ?, 
			    `latitude` = ?, 
			    `longitude` = ?, 
			    `address_city` = ?, 
			    `address_state` = ?, 
			    `address_zip` = ?, 
			    `address_line_1` = ?
		        WHERE case_id = ? ") )) 
        {
    		echo "Prepare failed: (" . $this->mysqli->errno . ") " . $this->mysqli->error;
		}

        return $update_stmt;
    }

}


error_reporting(E_ALL);
ini_set('display_errors','1');


/**
 * Process command line options
 */

$altkey = false;

$shortopts = "h";
$longopts = array(
    "help",
    "size::",    // limit
    "offset::",
    "max::",    // max_loops
    "order::"   // order
);

$options = getopt($shortopts, $longopts);

if ( array_key_exists('help',$options) || array_key_exists('h',$options)) {
    print "usage [--size=999] [--max=10000] [--offset=0] [[--order='case_id desc'] [--help]\n";
    print "   size - number of calls to grab at a time\n";
    print "   max  - number of time to grab calls\n";
    print "   order - sort order\n";
    print "   offset - where to start in the list\n";

    exit;
}
$limit = 999;
$offset = 0;
$max_loops = 1000;
$order = 'case_id desc';
print_r($options);
if (array_key_exists('size',$options)) $limit = $options['size'];
if (array_key_exists('offset',$options)) $offset = $options['offset'];
if (array_key_exists('max',$options)) $max_loops = $options['max'];
if (array_key_exists('order',$options)) $order = $options['order'];

if (!isset($argv)) {
    $argv = array();
}

print "\n\n";
print "######################################################################\n";
print "get311calls.php: \n";
print "       size: $limit\n";
print "     offset: $offset\n";
print "        max: $max_loops\n";
print "      order: $order\n";
print "######################################################################\n";

$calls = new ProcessCalls($limit, $offset, $max_loops, $order);

$calls->get_calls();
	
	
