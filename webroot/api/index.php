<?php
mysqli_report(MYSQLI_REPORT_STRICT); 

require "vendor/autoload.php";

$app = new \Slim\Slim();
$app->response()->header('Content-Type', 'application/json');

// JSONPMiddleware: If a GET parameter of callback is found
//                  wrap the responce in a JSONP callback function
$app->add(new \Slim\Extras\Middleware\JSONPMiddleware());

// Routing
$app->get('/neighborhoods', function () { load_for_pulldowns('neighborhoods'); });
$app->get('/requesttypes',  function () { load_for_pulldowns('request_types'); });
$app->get('/calls',         'getCalls');

$app->run();  // All headers need to be set before this
              // All echos are captured and then sent through the
              // $app->response 

// Grunt work
function load_for_pulldowns($table) {
    try {
        $data = get_pulldowns($table);
    } catch ( Exception  $e ) {
    	$data = ( object ) array( "error" => true, 'msg' => 'Error on server');
    }

    echo json_encode( $data ) ;
}


function get_pulldowns($table) {

    require '../../bin/config.php';

    try {
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    } catch (mysqli_sql_exception $e) { 
        error_log( $e->getMessage() . ' ' . __FILE__ . ' ' . __LINE__ ) ;
        throw new Exception("Unable to connect to database");    
    } 

	$mysqli->real_query("SELECT * FROM $table ORDER BY name -- " . __FILE__ . ' ' . __LINE__ );
    if ($mysqli->error) {
        error_log( $mysqli->error . ' ' . __FILE__ . ' ' . __LINE__ ) ;
        throw new Exception("Unable to execute database query:");    
    }

	$res = $mysqli->use_result();

    $data = array();

	while ($row = $res->fetch_assoc()) {
	    $data[] = ( object ) array( 'id' => $row['id'], 'name' => $row['name']);
	}

    return $data;
	
}


function getCalls () {

    require '../../bin/config.php';

    $data = array();

    try {
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    } catch (mysqli_sql_exception $e) { 
        error_log( $e->getMessage() . ' ' . __FILE__ . ' ' . __LINE__ ) ;
        throw new Exception("Unable to connect to database");    
    } 

    $departments    = load_descriptions($mysqli,'departments');
    $request_types  = load_descriptions($mysqli,'request_types');
    $neighborhoods  = load_descriptions($mysqli,'neighborhoods');
    $work_groups    = load_descriptions($mysqli,'work_groups');

    $mysqli->real_query(
        "SELECT * FROM three11_calls WHERE latitude <> '' ORDER BY case_id DESC LIMIT 100 -- " . 
        __FILE__ . ' ' . __LINE__ 
    );
    if ($mysqli->error) {
        error_log( $mysqli->error . ' ' . __FILE__ . ' ' . __LINE__ ) ;
        throw new Exception("Unable to execute database query:");    
    }

    $res = $mysqli->use_result();
    
    while ($row = $res->fetch_assoc()) {
        $row['department']      = $departments[$row['department_id']];
        $row['neighborhood']    = $neighborhoods[$row['neighborhood_id']];
        $row['work_group']      = $work_groups[$row['work_group_id']];
        $row['request_type']    = $request_types[$row['request_type_id']];
        $data[] = $row;
    }

    echo json_encode( $data ) ;
}

function load_descriptions(&$mysqli,$table) {

    $mysqli->real_query("SELECT * FROM $table order by name -- " . __FILE__ . ' ' . __LINE__ );
    if ($mysqli->error) {
        error_log( $mysqli->error . ' ' . __FILE__ . ' ' . __LINE__ ) ;
        throw new Exception("Unable to execute database query:");    
    }

    $res = $mysqli->use_result();
    $data = array();

    while ($row = $res->fetch_assoc()) {
        $data[$row['id']] = $row['name'];
    }

    return $data;
}

