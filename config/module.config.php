<?php

//die("here");

return array(
   
    'controllers' => array(
        'invokables' => array(
            'Generator\Controller\Index' => 'Generator\Controller\IndexController'
        ),
    ),
    
    'router' => array(
        'routes' => array(
            'generator' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/generate[/:action][/:id]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Generator\Controller\Index',
                        'action' => 'index',
                    ),
                ),
            ),
        ),
    ),
    
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    
);