<?php
/**
 * Collections let us define groups of routes that will all use the same controller.
 * We can also set the handler to be lazy loaded.  Collections can share a common prefix.
 * @var $cmsCollection
 */
// This is an Immeidately Invoked Function in php. The return value of the
// anonymous function will be returned to any file that "includes" it.
// e.g. $collection = include('example.php');
return call_user_func(
        function () {
            $cmsCollection = new \Phalcon\Mvc\Micro\Collection();
            $cmsCollection->
            // VERSION NUMBER SHOULD BE FIRST URL PARAMETER, ALWAYS
            setPrefix('/v1/frontend')
                ->setHandler(
                    '\DwComment\Modules\V1\Controllers\FrontendController')
                ->setLazy(true);
            // Set Access-Control-Allow headers.
            $cmsCollection->options('/', 'optionsBase');
            // $cmsCollection->options('/{id}', 'optionsOne');
            // First paramter is the route, which with the collection prefix
            // here would be GET /example/
            // Second paramter is the function name of the Controller.
            // This is exactly the same execution as GET, but the Response has
            // no body.
            // $cmsCollection->head('/', 'get');
            $cmsCollection->get('/', 'cms');
            // $id will be passed as a parameter to the Controller's specified
            // function
            // $cmsCollection->get('/{id:[0-9]+}', 'getOne');
            // $cmsCollection->head('/{id:[0-9]+}', 'getOne');
            // $cmsCollection->get('/', 'get');
            // $cmsCollection->delete('/{id:[0-9]+}', 'delete');
            // $cmsCollection->put('/{id:[0-9]+}', 'put');
            // $cmsCollection->patch('/{id:[0-9]+}', 'patch');
            return $cmsCollection;
        });