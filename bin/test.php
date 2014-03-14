<?php

require_once("socrata.php");

// https://data.kcmo.org/api/views/djv7-4q5r/rows.json?accessType=DOWNLOAD
$socrata = new Socrata("http://data.kcmo.org/api");
$response = $socrata->get("/views/djv7-4q5r/rows.json", array("max_rows"=>50));

print_r($response);
