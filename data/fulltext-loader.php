<?php
/**
 *  Uloží jednotlivé stránky do fulltext tabulky
 *
 *  Možno spustit CLI přes php fulltext-loader.php
 *  Pro 600 PDF cca 17000 stránek = 20 minut
 */





// absolute filesystem path to this web root
define('WWW_DIR', dirname(__FILE__).'/..');

// absolute filesystem path to the application root
define('APP_DIR', WWW_DIR . '/app');

// absolute filesystem path to the libraries
define('LIBS_DIR', WWW_DIR . '/libs');

// Load Nette Framework or autoloader generated by Composer
require LIBS_DIR . '/autoload.php';


// Configure application
$configurator = new Nette\Config\Configurator;

// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(APP_DIR . '/temp');
$configurator->createRobotLoader()
	->addDirectory(APP_DIR)
	->addDirectory(LIBS_DIR)
	->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(APP_DIR . '/config.neon');
if(file_exists(APP_DIR . '/config-server.neon'))
	$configurator->addConfig(APP_DIR . '/config-server.neon');
$container = $configurator->createContainer();

// Connect to the database
dibi::connect($container->parameters['database']);

error_reporting(E_ALL & ~E_NOTICE);

//------------------ do tricks: ------------

set_time_limit(3600);

// could be 10 times quicker
echo "Loading more data? ATTENTION: TEMPORARY DISABLE FULLTEXT INDEX!!! <br>\n\n";


$cisla = dibi::query('
	SELECT cislo.*
	FROM cislo
	LEFT JOIN `fulltext` ON cislo.id = cislo_id
	WHERE cislo_id IS NULL
	ORDER BY id
	');

foreach($cisla as $r)
{
	$cislo = new \Casopisy\Cislo($r);

	echo "Converting $cislo->id ($cislo->pocet_stran pages)...";
	$len = $cislo->indexFulltext();
	echo "$len bytes\n";
}

echo "\n\nDONE\n";



