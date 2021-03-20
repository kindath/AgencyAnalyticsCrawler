<?php

$router = $di->getShared('router');

// Define your routes here
$router->handle( str_replace( '/dev/AgencyAnalyticsCrawler/src/', '/', $_SERVER['REQUEST_URI'] ) );
