<?php

$app = new Silex\Application();

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/* Activation de doctrine */

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => 'pdo_sqlite',
        'path' => __DIR__ . '/../data/app.db',
    ),
));

use Doctrine\Common\Persistence\ObjectManager;

/* Parametres du site */

$app['siteName'] = 'MySitek';
$app['url'] = 'http://localhost/';
$app['user'] = 'mathieu.sge@hotmail.fr'; //TODO : à charger à partir des parametres
$app['debug'] = true;
$app['selected'] = ''; //module en cours (pour affichage lien actif)


/* Recuperation du template */

$sql = "SELECT * FROM templates WHERE selected = 1";
$retour = $app['db']->fetchAssoc($sql);
$app['template'] = $retour['name'];

/* Recuperation du module index */

$sql = "SELECT * FROM modules WHERE accueil = 1";
$retour = $app['db']->fetchAssoc($sql);
$app['index'] = $retour['lien'];



/* Recuperation des modules */

//front à 1 signifie que le module à une partie publique
$sql = "SELECT * FROM modules WHERE selected = 1 AND front = 1";
$app['modules_front'] = $app['db']->fetchAll($sql);

//front à 0 signifie module back
$sql = "SELECT * FROM modules WHERE selected = 1 AND front = 0";
$app['modules_back'] = $app['db']->fetchAll($sql);

//front à -1 signifie module uniquement admin
$sql = "SELECT * FROM modules WHERE selected = 1 AND front = -1";
$app['modules_admin'] = $app['db']->fetchAll($sql);



/* Securisation */

$app->register(new Silex\Provider\SecurityServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

include_once __DIR__ . '/../lib/model/UserProvider.php';
/* FIREWALL */
$app['security.firewalls'] = array(
    'user' => array(
        'pattern' => '^/admin/',
        'form' => array('login_path' => '/login', 'check_path' => '/admin/login_check'),
        'logout' => array('logout_path' => '/admin/logout'),
        'users' => $app->share(function () use ($app) {
            return new UserProvider($app['db']);
        }),
    ),
);

/* Login */

$app->register(new Silex\Provider\SessionServiceProvider());


$app->get('/login', function(Request $request) use ($app) {

    #echo (new \Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder())->encodePassword('foo', '');

    $app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.class_path' => __DIR__ . '/../vendor/Twig/lib',
        'twig.path' => array(__DIR__ . '/templates/' . $app['template'] . '/')
    ));

    return $app['twig']->render('login.twig', array(
                'error' => $app['security.last_error']($request),
                'last_username' => $app['session']->get('_security.last_username')
    ));
});





/* Routage */

$app->get('/', function() use ($app) {

    return $app->redirect('/' . $app['index']);
});

$app->get('/admin/', function() use ($app) {

    $app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.class_path' => __DIR__ . '/../vendor/Twig/lib',
        'twig.path' => array(__DIR__ . '/templates/' . $app['template'] . '/')
    ));

    return $app['twig']->render('admin.twig', array(
                'hello' => 'Hello world Admin !'
    ));
});

$app->get('/admin/achat/{type}/{file}', function ($type, $file) use ($app) {

    require_once __DIR__ . '/../lib/payplug/lib/Payplug.php';

    Payplug::setConfigFromFile(__DIR__ . '/../lib/payplug/parameters.json');

    $ipn = 'http://api.mysitek.com/payplug/ipn.php?user=' . $app['user'] . '&amp;type=' . $type . '&amp;module=' . $file . '';
    $install = $app['url'] . 'admin/install/' . $type . '/' . $file . '';

    //TODO #API : Récupération de l'objet module et remplir à partir la variable prix ci dessous :
    $prix = '0150';

    if ($prix == 0) {
        //TODO #TOKEN
        //execution de $ipn
        //l'api sait à partir du nom du module que le prix est de 0
        //Donc maj token               

        return $app->redirect('/admin/install/' . $type . '/' . $file . '');
    } else {


        //TODO : Récupération des variables ci dessous à partir des parametres en db client :
        $prenom = 'john';
        $nom = 'doe';

        $paymentUrl = PaymentUrl::generateUrl(array(
                    'amount' => $prix,
                    'currency' => 'EUR',
                    'ipnUrl' => $ipn,
                    'returnUrl' => $ipn,
                    'email' => $app['user'],
                    'firstName' => $prenom,
                    'lastName' => $nom
        ));
        
        header("Location: $paymentUrl");
        exit();

        return '';
    }
});

//TODO : interface message -> modal dans admin.twig avec message et activation passés en parametres
$app->get('/admin/install/{type}/{file}', function ($type, $file) use ($app) {

    require_once __DIR__ . '/../lib/model/Install.php';

    $app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.class_path' => __DIR__ . '/../vendor/Twig/lib',
        'twig.path' => array(__DIR__ . '/templates/' . $app['template'] . '/')
    ));

    //TODO #TOKEN : checkToken pour confirmer paiement si ok alors install
    //a voir car possible pb de timing, les deux scripts sont exécutés en meme tps à l'issu du paiement...
    //au pire ca installe (le client doit d'abord trouver l'url) et il se fera niquer lors du checkToken :P
    //peut également servir pour une future periode de test 
    
    $error = Install::installation($file, $type, $app);

    //TODO : redirection suite à l'instalation vers admin pour eviter un refresh de l'url (inoffensif mais chiant)
    //evite egalement la maj ci dessous, pour le moment ca pour afficher $error en attendant modal

    /*     * **** MAJ SUITE A INSTALLATION ***** */

    /* Recuperation du template */

    $sql = "SELECT * FROM templates WHERE selected = 1";
    $retour = $app['db']->fetchAssoc($sql);
    $app['template'] = $retour['name'];

    /* Recuperation du module index */

    $sql = "SELECT * FROM modules WHERE accueil = 1";
    $retour = $app['db']->fetchAssoc($sql);
    $app['index'] = $retour['lien'];

    /* Recuperation des modules */

    //front à 1 signifie que le module à une partie publique
    $sql = "SELECT * FROM modules WHERE selected = 1 AND front = 1";
    $app['modules_front'] = $app['db']->fetchAll($sql);

    //front à 0 signifie module back
    $sql = "SELECT * FROM modules WHERE selected = 1 AND front = 0";
    $app['modules_back'] = $app['db']->fetchAll($sql);

    //front à -1 signifie module uniquement admin
    $sql = "SELECT * FROM modules WHERE selected = 1 AND front = -1";
    $app['modules_admin'] = $app['db']->fetchAll($sql);

    if ($error == '') {
        return $app['twig']->render('admin.twig', array(
                    'hello' => 'Le fichier est maintenant installé'
        ));
    } else {
        return $app['twig']->render('admin.twig', array(
                    'hello' => $error
        ));
    }
});

//Routage des différents modules

foreach ($app['modules_back'] as $module) {

    include_once __DIR__ . '/modules/' . $module['lien'] . '/actions/controler-back.php';
}

foreach ($app['modules_admin'] as $module) {

    include_once __DIR__ . '/modules/admin/' . $module['lien'] . '/actions/controler-back.php';
}


foreach ($app['modules_front'] as $module) {

    include_once __DIR__ . '/modules/' . $module['lien'] . '/actions/controler-back.php';
    include_once __DIR__ . '/modules/' . $module['lien'] . '/actions/controler-front.php';
}



return $app;



