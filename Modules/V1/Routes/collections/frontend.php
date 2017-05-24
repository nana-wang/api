<?php
/**
 * Collections let us define groups of routes that will all use the same controller.
 * We can also set the handler to be lazy loaded.  Collections can share a common prefix.
 * @var $frontendCollection
 */
// This is an Immeidately Invoked Function in php. The return value of the
// anonymous function will be returned to any file that "includes" it.
// e.g. $collection = include('example.php');
return call_user_func(
        function () {
            $frontendCollection = new \Phalcon\Mvc\Micro\Collection();
            $frontendCollection->
            // VERSION NUMBER SHOULD BE FIRST URL PARAMETER, ALWAYS
            setPrefix('/v1/frontend')
                ->setHandler(
                    '\DwComment\Modules\V1\Controllers\FrontendController')
                ->setLazy(true);
            // Set Access-Control-Allow headers.
            $frontendCollection->options('/', 'optionsBase');
            $frontendCollection->get('/', 'comment');
            $frontendCollection->get('/tags', 'tags');
            $frontendCollection->get('/queue', 'queue');
            return $frontendCollection;
        });