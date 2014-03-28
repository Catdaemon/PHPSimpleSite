<?php
	// https://github.com/Catdaemon/PHPSimpleSite/

	require('db.class.php');
	require('dbo.class.php');
	require('page.class.php');
	require('router.class.php');

	// AutoLoader for models
	spl_autoload_register(function ($class) {
		@include 'classes/models/' . $class . '.php';
	});