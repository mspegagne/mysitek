<?php
$projet = $app['controllers_factory'];


$projet->get('/', function() use ($app) {
    
    /* Activation de twig avec les templates du module */

    $app->register(new Silex\Provider\TwigServiceProvider(), array(
            'twig.class_path'    => __DIR__ . '/../../../../vendor/Twig/lib',
            'twig.path' => array(__DIR__.'/../../../templates/'.$app['template'].'/',
                                 __DIR__.'/../templates/',)
    )); 

    //Recuperation de l'user
    $app['user'] =  $app['security']->getToken()->getUser() ;
    
    $app['selected']='projet';
    
    return $app['twig']->render('index.twig', array(
        'hello' => 'Hello world 3 !'
    ));
    
});

$app->mount('/projet', $projet);