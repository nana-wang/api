<?php

/**
 * Collections let us define groups of routes that will all use the same controller.
 * We can also set the handler to be lazy loaded.  Collections can share a common prefix.
 * @var $commentCollection
 */

// This is an Immeidately Invoked Function in php. The return value of the
// anonymous function will be returned to any file that "includes" it.
// e.g. $collection = include('example.php');
return call_user_func(
        function () {
            
            $commentCollection = new \Phalcon\Mvc\Micro\Collection();
            
            $commentCollection->
            // VERSION NUMBER SHOULD BE FIRST URL PARAMETER, ALWAYS
            setPrefix('/v1/api')
                ->
            // Must be a string in order to support lazy loading
            setHandler('\DwComment\Modules\V1\Controllers\ApiController')
                ->setLazy(true);
            
            // Set Access-Control-Allow headers.
            $commentCollection->options('/', 'optionsBase');
            $commentCollection->options('/{id}', 'optionsOne');
            
            // First parameter is the route, which with the collection prefix
            // here would be GET /example/
            // Second parameter is the function name of the Controller.
            $commentCollection->get('/', 'get');
            $commentCollection->get('/comment', 'comment');
            // This is exactly the same execution as GET, but the Response has
            // no body.
            $commentCollection->head('/', 'get');
            
            // $id will be passed as a parameter to the Controller's specified
            // function
            $commentCollection->get('/{id:[0-9]+}', 'getOne');
            $commentCollection->head('/{id:[0-9]+}', 'getOne');
            $commentCollection->post('/', 'post');
            $commentCollection->delete('/{id:[0-9]+}', 'delete');
            $commentCollection->put('/{id:[0-9]+}', 'put');
            $commentCollection->patch('/{id:[0-9]+}', 'patch');
            
            return $commentCollection;
        });