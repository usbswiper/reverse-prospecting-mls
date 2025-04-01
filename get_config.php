<?php
require_once (dirname(__FILE__).'/bootstrapper.php');

ApiBase::cors();
die(json_encode(['test_mode' => DEBUG_MODE]));