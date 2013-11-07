<?php

/**
 * My Application bootstrap file.
 */
use Nette\Application\Routers\Route,
    Nette\Application\Routers\RouteList,
    Nette\Application\Routers\SimpleRouter,
    \Nette\Application\UI\Presenter;

// Load Nette Framework or autoloader generated by Composer
require LIBS_DIR . '/autoload.php';


// Configure application
$configurator = new Nette\Config\Configurator;

// Enable Nette Debugger for error visualisation & logging
//Nette\Diagnostics\Debugger::$productionMode = true;
$configurator->enableDebugger(__DIR__ . '/log');
//$configurator->setDebugMode(false);

function barDump($x) {
    Nette\Diagnostics\Debugger::barDump($x);
}

// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(__DIR__ . '/temp');
$configurator->createRobotLoader()
        ->addDirectory(APP_DIR)
        ->addDirectory(LIBS_DIR)
        ->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config.neon');
if(file_exists(__DIR__ . '/config-server.neon'))
	$configurator->addConfig(__DIR__ . '/config-server.neon');
$container = $configurator->createContainer();

// Connect to the database
dibi::connect($container->params['database']);



// Setup router
$container->router[] = $adminRouter = new RouteList('Admin');
$adminRouter[] = new Route('admin/<casopis>/<presenter>[/<action>][/<id>]', array(
            'casopis' => array(
                Route::VALUE => 0,
                Route::FILTER_TABLE => \Casopisy\CasopisModel::getCasopisyURL(),
            ),
            'presenter' => 'Casopis',
            'action' => 'default',
        ));


$container->router[] = $frontRouter = new RouteList('Front');
$frontRouter[] = new Route('data/thumbs/<id>-<page>[-<hash>][.<opts>].png', 'File:preview');
$frontRouter[] = new Route('[index.php]', 'Homepage:default');
$frontRouter[] = new Route('login[/<action>]', "Login:default");
$frontRouter[] = new Route('<casopis>/tagy/<id .*>', array(
            'casopis' => array(
                Route::FILTER_TABLE => \Casopisy\CasopisModel::getCasopisyURL(),
            ),
            'presenter' => 'Casopis',
            'action' => 'default',
        ));
$frontRouter[] = new Route('<casopis svetylko|kmen|skaut|skauting|svet|kapitanska-posta|mokre-vlcata|mokre-skauti|publikace>/<presenter>[/<id>][/<action>]', array(
            'casopis' => array(
                Route::FILTER_TABLE => \Casopisy\CasopisModel::getCasopisyURL(),
            ),
            'presenter' => 'Casopis',
            'action' => 'default',
        ));

$frontRouter[] = new Route('<presenter>/<action>[.php][/<id>]', "Homepage:default");

Presenter::extensionMethod('isAdminModule', function (Presenter $that) {
      return strpos($that->getName(), "Admin:") === 0;
    });

Nette\Templating\FileTemplate::extensionMethod('modified', function ($that, $s) {
			return $s . "?". dechex(filemtime(WWW_DIR.$s));
		});

Nette\Templating\FileTemplate::extensionMethod('linkify', function ($that, $s) {
	$s = htmlspecialchars($s);
	$s = nl2br($s);
	//$s = preg_replace('~==([^=]+)==[\r\n]+~is', '<h2>\\1</h2>', $s);
	$s = preg_replace('~\*([^*]+)\*~iU', '<b>\\1</b>', $s);
	return preg_replace('~https?://([^ \n\r\t]+)~is', '<a href="http://\\1" target="_blank">\\1</a>', $s);
});


// Configure and run the application!
//$container->application->catchExceptions = true;
$container->application->run();
